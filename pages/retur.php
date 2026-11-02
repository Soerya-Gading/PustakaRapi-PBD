<?php
declare(strict_types=1);

/**
 * PustakaRapi — Halaman Retur Barang
 */

auth_require_page('retur');

$canManage = auth_can_manage('retur');

// Ambil riwayat transaksi retur
$stmt = $pdo->query('
    SELECT r.idretur, r.created_at, r.idpenerimaan, u.username,
           COUNT(dr.iddetail_retur) AS item_count,
           COALESCE(SUM(dr.jumlah), 0) AS total_qty
    FROM retur r
    LEFT JOIN `user` u ON r.iduser = u.iduser
    LEFT JOIN detail_retur dr ON r.idretur = dr.idretur
    GROUP BY r.idretur, r.created_at, r.idpenerimaan, u.username
    ORDER BY r.idretur DESC
');
$daftarRetur = $stmt->fetchAll();

// Detail retur per transaksi untuk modal
$detailByRetur = [];
$stmtDetail = $pdo->query('
    SELECT dr.idretur, dr.jumlah, dr.alasan, b.nama AS judul_buku, s.nama_satuan
    FROM detail_retur dr
    LEFT JOIN detail_penerimaan dp ON dr.iddetail_penerimaan = dp.iddetail_penerimaan
    LEFT JOIN barang b ON dp.barang_idbarang = b.idbarang
    LEFT JOIN satuan s ON b.idsatuan = s.idsatuan
');
foreach ($stmtDetail->fetchAll() as $d) {
    $detailByRetur[(int)$d['idretur']][] = $d;
}

// Ambil daftar penerimaan yang dapat diretur
$stmtPenerimaan = $pdo->query('
    SELECT pr.idpenerimaan, pr.created_at, v.nama_vendor
    FROM penerimaan pr
    LEFT JOIN pengadaan pg ON pr.idpengadaan = pg.idpengadaan
    LEFT JOIN vendor v ON pg.vendor_idvendor = v.idvendor
    ORDER BY pr.idpenerimaan DESC
');
$daftarPenerimaan = $stmtPenerimaan->fetchAll();

// Mapping item dari tiap penerimaan untuk form retur
$penerimaanItemsMap = [];
if (!empty($daftarPenerimaan)) {
    $stmtItems = $pdo->query('
        SELECT dp.iddetail_penerimaan, dp.idpenerimaan, dp.barang_idbarang, dp.jumlah_terima,
               b.nama AS judul_buku, s.nama_satuan
        FROM detail_penerimaan dp
        LEFT JOIN barang b ON dp.barang_idbarang = b.idbarang
        LEFT JOIN satuan s ON b.idsatuan = s.idsatuan
    ');
    foreach ($stmtItems->fetchAll() as $it) {
        $penerimaanItemsMap[(int)$it['idpenerimaan']][] = $it;
    }
}

include dirname(__DIR__) . '/includes/layout-header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title-group">
            <h2>Retur Pengembalian Buku</h2>
            <p class="card-subtitle">
                Pengembalian buku rusak atau cacat ke vendor berdasarkan berkas penerimaan barang. Mengurangi persediaan pada <code>kartu_stok</code>.
            </p>
        </div>
        <?php if ($canManage): ?>
            <button type="button" class="btn btn-primary" data-open-modal="modal-retur-baru">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>
                </svg>
                Proses Retur Baru
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">No. Retur</th>
                    <th>Waktu Retur</th>
                    <th>Ref. Penerimaan</th>
                    <th>Petugas Pengurus</th>
                    <th style="text-align:center;">Total Buku Diretur</th>
                    <th style="width: 100px; text-align:right;">Rincian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarRetur)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <h3>Belum Ada Transaksi Retur</h3>
                                <p>Pengembalian barang ke pihak vendor atau penerbit akan tercatat di sini.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarRetur as $ret): ?>
                        <tr>
                            <td class="mono" style="font-weight:700; color:var(--text-subtle);">#RET-<?= (int)$ret['idretur'] ?></td>
                            <td style="font-size:0.86rem;"><?= format_tanggal($ret['created_at']) ?></td>
                            <td class="mono">RC #<?= (int)$ret['idpenerimaan'] ?></td>
                            <td style="font-weight:600;"><?= e($ret['username']) ?></td>
                            <td class="mono" style="text-align:center; font-weight:700; color:var(--warning-text);">
                                <?= (int)$ret['total_qty'] ?> Eksemplar
                            </td>
                            <td style="text-align:right;">
                                <button type="button" class="btn btn-secondary btn-sm" data-open-modal="modal-retur-detail-<?= (int)$ret['idretur'] ?>">
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

<!-- ================= Modals Detail Retur ================= -->
<?php foreach ($daftarRetur as $ret): ?>
    <?php $returItems = $detailByRetur[(int)$ret['idretur']] ?? []; ?>
    <div class="modal-backdrop" id="modal-retur-detail-<?= (int)$ret['idretur'] ?>" role="dialog" aria-modal="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-header">
                <div>
                    <span class="micro-label">Bukti Retur</span>
                    <h3 class="modal-title">Retur #RET-<?= (int)$ret['idretur'] ?></h3>
                </div>
                <button type="button" class="btn-ghost" data-close-modal style="min-height:auto; padding:4px;" aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display:flex; justify-content:space-between; margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid var(--border-light); font-size:0.88rem;">
                    <div>
                        <strong>Ref. Penerimaan:</strong> Penerimaan #RC-<?= (int)$ret['idpenerimaan'] ?><br>
                        <strong>Petugas:</strong> <?= e($ret['username']) ?>
                    </div>
                    <div style="text-align:right;">
                        <strong>Tanggal Retur:</strong> <?= format_tanggal($ret['created_at']) ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Judul Buku</th>
                                <th style="width:110px; text-align:center;">Jumlah Retur</th>
                                <th>Alasan Pengembalian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returItems as $item): ?>
                                <tr>
                                    <td style="font-weight:600;"><?= e($item['judul_buku']) ?></td>
                                    <td class="mono" style="text-align:center; font-weight:700; color:var(--danger-text);">
                                        -<?= (int)$item['jumlah'] ?> <?= e($item['nama_satuan']) ?>
                                    </td>
                                    <td style="color:var(--text-muted);"><?= e($item['alasan']) ?></td>
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

<!-- ================= Modal Form Retur Baru ================= -->
<?php if ($canManage): ?>
<div class="modal-backdrop" id="modal-retur-baru" role="dialog" aria-modal="true" aria-labelledby="modal-retur-title">
    <div class="modal-dialog modal-lg">
        <form method="post" action="?action=retur_simpan" id="form-retur">
            <?= csrf_field() ?>

            <div class="modal-header">
                <div>
                    <span class="micro-label">Form Operasional</span>
                    <h3 class="modal-title" id="modal-retur-title">Proses Pengembalian / Retur Buku</h3>
                </div>
                <button type="button" class="btn-ghost" data-close-modal style="min-height:auto; padding:4px;" aria-label="Tutup">&times;</button>
            </div>

            <div class="modal-body">
                <?php if (empty($daftarPenerimaan)): ?>
                    <div class="empty-state" style="padding:24px 0;">
                        <h3>Belum Ada Penerimaan Barang</h3>
                        <p>Retur hanya dapat diproses setelah ada barang yang diterima dari vendor.</p>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label class="form-label" for="select-retur-penerimaan">Pilih Penerimaan Barang Asal <span class="req">*</span></label>
                        <select name="idpenerimaan" id="select-retur-penerimaan" class="form-control" required>
                            <option value="">-- Pilih Berkas Penerimaan --</option>
                            <?php foreach ($daftarPenerimaan as $pr): ?>
                                <option value="<?= (int)$pr['idpenerimaan'] ?>">
                                    RC #<?= (int)$pr['idpenerimaan'] ?> — <?= e($pr['nama_vendor'] ?? 'Vendor') ?> (<?= format_tanggal($pr['created_at']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="container-retur-items" style="display:none; margin-top:20px;">
                        <label class="micro-label">Tentukan Jumlah & Alasan Retur</label>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Judul Buku</th>
                                        <th style="width:110px; text-align:center;">Diterima</th>
                                        <th style="width:110px;">Qty Retur</th>
                                        <th>Alasan Pengembalian</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-retur-items"></tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <?php if (!empty($daftarPenerimaan)): ?>
                    <button type="submit" class="btn btn-primary" id="btn-submit-retur" disabled>Konfirmasi Retur & Kurangi Stok</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
const penerimaanItemsMap = <?= json_encode($penerimaanItemsMap) ?>;

document.addEventListener('DOMContentLoaded', () => {
    const selectPr = document.getElementById('select-retur-penerimaan');
    const container = document.getElementById('container-retur-items');
    const tbody = document.getElementById('tbody-retur-items');
    const submitBtn = document.getElementById('btn-submit-retur');

    if (selectPr && container && tbody) {
        selectPr.addEventListener('change', () => {
            const prId = selectPr.value;
            tbody.innerHTML = '';

            if (!prId || !penerimaanItemsMap[prId]) {
                container.style.display = 'none';
                if (submitBtn) submitBtn.disabled = true;
                return;
            }

            container.style.display = 'block';
            if (submitBtn) submitBtn.disabled = false;

            penerimaanItemsMap[prId].forEach((item, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="font-weight:600;">
                        ${item.judul_buku}
                        <input type="hidden" name="items[${index}][iddetail_penerimaan]" value="${item.iddetail_penerimaan}">
                        <input type="hidden" name="items[${index}][idbarang]" value="${item.barang_idbarang}">
                    </td>
                    <td class="mono" style="text-align:center;">${item.jumlah_terima} ${item.nama_satuan}</td>
                    <td>
                        <input type="number" name="items[${index}][jumlah]" 
                               class="form-control mono" min="0" max="${item.jumlah_terima}" value="0">
                    </td>
                    <td>
                        <input type="text" name="items[${index}][alasan]" 
                               class="form-control" maxlength="200" placeholder="Contoh: Halaman sobek, salah cetak">
                    </td>
                `;
                tbody.appendChild(tr);
            });
        });
    }
});
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/layout-footer.php'; ?>

