<?php
declare(strict_types=1);

/**
 * PustakaRapi — Halaman Penerimaan Barang & Dampak Stok
 */

auth_require_page('penerimaan');

$canManage = auth_can_manage('penerimaan');

// Daftar histori penerimaan
$stmt = $pdo->query('
    SELECT pr.idpenerimaan, pr.created_at, pr.status, pr.idpengadaan,
           u.username, v.nama_vendor,
           COUNT(dp.iddetail_penerimaan) AS total_item,
           COALESCE(SUM(dp.jumlah_terima), 0) AS total_qty
    FROM penerimaan pr
    LEFT JOIN `user` u ON pr.iduser = u.iduser
    LEFT JOIN pengadaan pg ON pr.idpengadaan = pg.idpengadaan
    LEFT JOIN vendor v ON pg.vendor_idvendor = v.idvendor
    LEFT JOIN detail_penerimaan dp ON pr.idpenerimaan = dp.idpenerimaan
    GROUP BY pr.idpenerimaan, pr.created_at, pr.status, pr.idpengadaan, u.username, v.nama_vendor
    ORDER BY pr.idpenerimaan DESC
');
$daftarPenerimaan = $stmt->fetchAll();

// Detail penerimaan per ID untuk modal rincian
$detailByPenerimaan = [];
$stmtDetail = $pdo->query('
    SELECT dp.idpenerimaan, dp.jumlah_terima, dp.harga_satuan_terima, dp.sub_total_terima,
           b.nama AS judul_buku, s.nama_satuan
    FROM detail_penerimaan dp
    LEFT JOIN barang b ON dp.barang_idbarang = b.idbarang
    LEFT JOIN satuan s ON b.idsatuan = s.idsatuan
');
foreach ($stmtDetail->fetchAll() as $d) {
    $detailByPenerimaan[(int)$d['idpenerimaan']][] = $d;
}

// Ambil pengadaan yang masih berstatus 'P' (Pending / Belum Diterima)
$stmtPendingPO = $pdo->query('
    SELECT p.idpengadaan, p.timestamp, v.nama_vendor
    FROM pengadaan p
    LEFT JOIN vendor v ON p.vendor_idvendor = v.idvendor
    WHERE p.status = "P"
    ORDER BY p.idpengadaan DESC
');
$pengadaanPending = $stmtPendingPO->fetchAll();

// Siapkan data detail item untuk tiap PO pending dalam JSON agar JS dapat render dinamis
$poItemsMap = [];
if (!empty($pengadaanPending)) {
    $stmtPoItems = $pdo->query('
        SELECT dp.idpengadaan, dp.idbarang,
               dp.jumlah - COALESCE((SELECT SUM(dr.jumlah_terima)
                   FROM detail_penerimaan dr JOIN penerimaan pr ON pr.idpenerimaan=dr.idpenerimaan
                   WHERE pr.idpengadaan=dp.idpengadaan AND dr.barang_idbarang=dp.idbarang),0) AS jumlah,
               dp.harga_satuan, b.nama AS judul_buku, s.nama_satuan
        FROM detail_pengadaan dp
        LEFT JOIN barang b ON dp.idbarang = b.idbarang
        LEFT JOIN satuan s ON b.idsatuan = s.idsatuan
        HAVING jumlah>0
    ');
    foreach ($stmtPoItems->fetchAll() as $pi) {
        $stk = BackendAdapter::hitungStokBuku($pdo, (int)$pi['idbarang']);
        $pi['stok_saat_ini'] = $stk['tercatat'] ? $stk['stok'] : 0;
        $poItemsMap[(int)$pi['idpengadaan']][] = $pi;
    }
}

include dirname(__DIR__) . '/includes/layout-header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title-group">
            <h2>Penerimaan Barang (Goods Receipt)</h2>
            <p class="card-subtitle">
                Pencatatan penerimaan fisik buku dari vendor. Setiap barang yang diterima otomatis menambah persediaan di <code>kartu_stok</code>.
            </p>
        </div>
        <?php if ($canManage): ?>
            <button type="button" class="btn btn-primary" data-open-modal="modal-terima-baru">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
                Terima Pengadaan Baru
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">No. Terima</th>
                    <th>Waktu Terima</th>
                    <th>Ref. Pengadaan</th>
                    <th>Vendor</th>
                    <th>Petugas Gudang</th>
                    <th style="text-align:center;">Jumlah Buku</th>
                    <th style="width: 120px;">Status</th>
                    <th style="width: 100px; text-align:right;">Rincian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarPenerimaan)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <h3>Belum Ada Transaksi Penerimaan</h3>
                                <p>Penerimaan barang dari pengadaan yang telah diajukan akan tampil di sini.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarPenerimaan as $rec): ?>
                        <tr>
                            <td class="mono" style="font-weight:700; color:var(--text-subtle);">#RC-<?= (int)$rec['idpenerimaan'] ?></td>
                            <td style="font-size:0.86rem;"><?= format_tanggal($rec['created_at']) ?></td>
                            <td class="mono">PO #<?= (int)$rec['idpengadaan'] ?></td>
                            <td style="font-weight:600;"><?= e($rec['nama_vendor'] ?? '—') ?></td>
                            <td><?= e($rec['username']) ?></td>
                            <td class="mono" style="text-align:center; font-weight:700;">
                                <?= (int)$rec['total_qty'] ?> Eksemplar
                            </td>
                            <td><?= render_badge_status((string)$rec['status'], 'transaksi') ?></td>
                            <td style="text-align:right;">
                                <button type="button" class="btn btn-secondary btn-sm" data-open-modal="modal-rec-detail-<?= (int)$rec['idpenerimaan'] ?>">
                                    Detail
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= Modals Detail Penerimaan ================= -->
<?php foreach ($daftarPenerimaan as $rec): ?>
    <?php $recItems = $detailByPenerimaan[(int)$rec['idpenerimaan']] ?? []; ?>
    <div class="modal-backdrop" id="modal-rec-detail-<?= (int)$rec['idpenerimaan'] ?>" role="dialog" aria-modal="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-header">
                <div>
                    <span class="micro-label">Bukti Terima Gudang</span>
                    <h3 class="modal-title">Penerimaan #RC-<?= (int)$rec['idpenerimaan'] ?></h3>
                </div>
                <button type="button" class="btn-ghost" data-close-modal style="min-height:auto; padding:4px;" aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display:flex; justify-content:space-between; margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid var(--border-light); font-size:0.88rem;">
                    <div>
                        <strong>No. PO:</strong> PO #<?= (int)$rec['idpengadaan'] ?><br>
                        <strong>Vendor:</strong> <?= e($rec['nama_vendor']) ?>
                    </div>
                    <div style="text-align:right;">
                        <strong>Tanggal Diterima:</strong> <?= format_tanggal($rec['created_at']) ?><br>
                        <strong>Penerima:</strong> <?= e($rec['username']) ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Judul Buku</th>
                                <th style="width:120px; text-align:center;">Qty Diterima</th>
                                <th style="width:140px; text-align:right;">Harga Satuan</th>
                                <th style="width:150px; text-align:right;">Subtotal Terima</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recItems as $item): ?>
                                <tr>
                                    <td style="font-weight:600;"><?= e($item['judul_buku']) ?></td>
                                    <td class="mono" style="text-align:center; font-weight:700; color:var(--success-text);">
                                        +<?= (int)$item['jumlah_terima'] ?> <?= e($item['nama_satuan']) ?>
                                    </td>
                                    <td class="mono" style="text-align:right;"><?= rupiah($item['harga_satuan_terima']) ?></td>
                                    <td class="mono" style="text-align:right; font-weight:600;"><?= rupiah($item['sub_total_terima']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Tutup</button>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- ================= Modal Form Penerimaan Baru ================= -->
<?php if ($canManage): ?>
<div class="modal-backdrop" id="modal-terima-baru" role="dialog" aria-modal="true" aria-labelledby="modal-terima-title">
    <div class="modal-dialog modal-lg">
        <form method="post" action="?action=penerimaan_simpan" id="form-penerimaan">
            <?= csrf_field() ?>

            <div class="modal-header">
                <div>
                    <span class="micro-label">Operasional Gudang</span>
                    <h3 class="modal-title" id="modal-terima-title">Penerimaan Barang Dari Pengadaan</h3>
                </div>
                <button type="button" class="btn-ghost" data-close-modal style="min-height:auto; padding:4px;" aria-label="Tutup">&times;</button>
            </div>

            <div class="modal-body">
                <?php if (empty($pengadaanPending)): ?>
                    <div class="empty-state" style="padding:24px 0;">
                        <h3>Tidak Ada Pengadaan Menunggu Penerimaan</h3>
                        <p>Semua pesanan pengadaan (PO) telah selesai diproses atau belum ada pengadaan baru.</p>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label class="form-label" for="select-po-terima">Pilih Pengadaan (PO) Yang Datang <span class="req">*</span></label>
                        <select name="idpengadaan" id="select-po-terima" class="form-control" required>
                            <option value="">-- Pilih Nomor PO --</option>
                            <?php foreach ($pengadaanPending as $po): ?>
                                <option value="<?= (int)$po['idpengadaan'] ?>">
                                    PO #<?= (int)$po['idpengadaan'] ?> — <?= e($po['nama_vendor']) ?> (<?= format_tanggal($po['timestamp']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="container-po-items" style="display:none; margin-top:20px;">
                        <label class="micro-label">Barang Diterima & Preview Dampak Stok</label>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Judul Buku</th>
                                        <th style="width:110px; text-align:center;">Sisa Pesanan</th>
                                        <th style="width:120px;">Diterima (Qty)</th>
                                        <th style="width:140px; text-align:center;">Dampak Stok</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-po-items"></tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <?php if (!empty($pengadaanPending)): ?>
                    <button type="submit" class="btn btn-primary" id="btn-submit-terima" disabled>Konfirmasi Terima & Update Stok</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
const poItemsMap = <?= json_encode($poItemsMap) ?>;

document.addEventListener('DOMContentLoaded', () => {
    const selectPo = document.getElementById('select-po-terima');
    const container = document.getElementById('container-po-items');
    const tbody = document.getElementById('tbody-po-items');
    const submitBtn = document.getElementById('btn-submit-terima');

    if (selectPo && container && tbody) {
        selectPo.addEventListener('change', () => {
            const poId = selectPo.value;
            tbody.innerHTML = '';

            if (!poId || !poItemsMap[poId]) {
                container.style.display = 'none';
                if (submitBtn) submitBtn.disabled = true;
                return;
            }

            container.style.display = 'block';
            if (submitBtn) submitBtn.disabled = false;

            poItemsMap[poId].forEach((item, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="font-weight:600;">
                        ${item.judul_buku}
                        <input type="hidden" name="items[${index}][idbarang]" value="${item.idbarang}">
                        <input type="hidden" name="items[${index}][harga_satuan]" value="${item.harga_satuan}">
                    </td>
                    <td class="mono" style="text-align:center;">${item.jumlah} ${item.nama_satuan}</td>
                    <td>
                        <input type="number" name="items[${index}][jumlah_terima]" 
                               class="form-control mono item-terima-qty" 
                               min="1" max="${item.jumlah}" value="${item.jumlah}" required>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge badge-success item-stock-preview">
                            ${item.stok_saat_ini} &rarr; ${item.stok_saat_ini + item.jumlah}
                        </span>
                    </td>
                `;

                const qtyInput = tr.querySelector('.item-terima-qty');
                const previewBadge = tr.querySelector('.item-stock-preview');

                qtyInput.addEventListener('input', () => {
                    const terima = parseInt(qtyInput.value || 0, 10);
                    const newStock = item.stok_saat_ini + terima;
                    previewBadge.innerHTML = `${item.stok_saat_ini} &rarr; ${newStock}`;
                });

                tbody.appendChild(tr);
            });
        });
    }
});
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/layout-footer.php'; ?>

