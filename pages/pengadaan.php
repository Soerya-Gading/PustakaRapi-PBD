<?php
declare(strict_types=1);

/**
 * PustakaRapi — Halaman Transaksi Pengadaan
 */

auth_require_page('pengadaan');

$canManage = auth_can_manage('pengadaan');

// Ambil riwayat transaksi pengadaan
$stmt = $pdo->query('
    SELECT p.idpengadaan, p.timestamp, p.status, p.subtotal_nilai, p.ppn, p.total_nilai,
           v.nama_vendor, u.username,
           COUNT(dp.iddetail_pengadaan) AS item_count
    FROM pengadaan p
    LEFT JOIN vendor v ON p.vendor_idvendor = v.idvendor
    LEFT JOIN `user` u ON p.user_iduser = u.iduser
    LEFT JOIN detail_pengadaan dp ON p.idpengadaan = dp.idpengadaan
    GROUP BY p.idpengadaan, p.timestamp, p.status, p.subtotal_nilai, p.ppn, p.total_nilai, v.nama_vendor, u.username
    ORDER BY p.idpengadaan DESC
');
$daftarPengadaan = $stmt->fetchAll();

// Ambil detail pengadaan per transaksi untuk modal detail
$detailByPengadaan = [];
$stmtDetail = $pdo->query('
    SELECT dp.idpengadaan, dp.jumlah, dp.harga_satuan, dp.sub_total, b.nama AS judul_buku, s.nama_satuan
    FROM detail_pengadaan dp
    LEFT JOIN barang b ON dp.idbarang = b.idbarang
    LEFT JOIN satuan s ON b.idsatuan = s.idsatuan
');
foreach ($stmtDetail->fetchAll() as $d) {
    $detailByPengadaan[(int)$d['idpengadaan']][] = $d;
}

// Data referensi untuk form pengadaan baru
$daftarVendor = $pdo->query('SELECT idvendor, nama_vendor FROM vendor WHERE status = "A" ORDER BY nama_vendor ASC')->fetchAll();
$daftarBuku = $pdo->query('SELECT idbarang, nama, harga FROM barang WHERE status = 1 ORDER BY nama ASC')->fetchAll();

include dirname(__DIR__) . '/includes/layout-header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title-group">
            <h2>Pengadaan Buku (Purchase Order)</h2>
            <p class="card-subtitle">
                Alur pengadaan pasokan buku dari vendor penerbit sebelum barang diterima di gudang.
            </p>
        </div>
        <?php if ($canManage): ?>
            <button type="button" class="btn btn-primary" data-open-modal="modal-pengadaan-baru">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Buat Pengadaan Baru
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">No. PO</th>
                    <th>Tanggal Pengadaan</th>
                    <th>Vendor Penerbit</th>
                    <th>Diajukan Oleh</th>
                    <th>Total Item</th>
                    <th style="text-align:right;">Total Nilai (Inc. PPN)</th>
                    <th style="width: 120px;">Status</th>
                    <th style="width: 100px; text-align:right;">Rincian</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarPengadaan)): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <h3>Belum Ada Transaksi Pengadaan</h3>
                                <p>Silakan buat pesanan pengadaan baru kepada vendor atau penerbit.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarPengadaan as $po): ?>
                        <tr>
                            <td class="mono" style="font-weight:700; color:var(--text-subtle);">#PO-<?= (int)$po['idpengadaan'] ?></td>
                            <td style="font-size:0.86rem;"><?= format_tanggal($po['timestamp']) ?></td>
                            <td style="font-weight:600; color:var(--text-main);"><?= e($po['nama_vendor'] ?? '—') ?></td>
                            <td><?= e($po['username'] ?? '—') ?></td>
                            <td>
                                <span class="badge badge-muted"><?= (int)$po['item_count'] ?> Judul Buku</span>
                            </td>
                            <td class="mono" style="text-align:right; font-weight:700; color:var(--text-main);">
                                <?= rupiah($po['total_nilai']) ?>
                            </td>
                            <td><?= render_badge_status((string)$po['status'], 'transaksi') ?></td>
                            <td style="text-align:right;">
                                <button type="button" class="btn btn-secondary btn-sm" data-open-modal="modal-po-detail-<?= (int)$po['idpengadaan'] ?>">
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

<!-- ================= Modals Detail Pengadaan ================= -->
<?php foreach ($daftarPengadaan as $po): ?>
    <?php $poItems = $detailByPengadaan[(int)$po['idpengadaan']] ?? []; ?>
    <div class="modal-backdrop" id="modal-po-detail-<?= (int)$po['idpengadaan'] ?>" role="dialog" aria-modal="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-header">
                <div>
                    <span class="micro-label">Purchase Order</span>
                    <h3 class="modal-title">Rincian Pengadaan #PO-<?= (int)$po['idpengadaan'] ?></h3>
                </div>
                <button type="button" class="btn-ghost" data-close-modal style="min-height:auto; padding:4px;" aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display:flex; justify-content:space-between; margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid var(--border-light); font-size:0.88rem;">
                    <div>
                        <strong>Vendor:</strong> <?= e($po['nama_vendor']) ?><br>
                        <strong>Pembuat:</strong> <?= e($po['username']) ?>
                    </div>
                    <div style="text-align:right;">
                        <strong>Tanggal:</strong> <?= format_tanggal($po['timestamp']) ?><br>
                        <strong>Status:</strong> <?= render_badge_status((string)$po['status'], 'transaksi') ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Judul Buku</th>
                                <th style="width:100px; text-align:center;">Jumlah</th>
                                <th style="width:140px; text-align:right;">Harga Satuan</th>
                                <th style="width:150px; text-align:right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($poItems as $item): ?>
                                <tr>
                                    <td style="font-weight:600;"><?= e($item['judul_buku']) ?></td>
                                    <td class="mono" style="text-align:center;"><?= (int)$item['jumlah'] ?> <?= e($item['nama_satuan']) ?></td>
                                    <td class="mono" style="text-align:right;"><?= rupiah($item['harga_satuan']) ?></td>
                                    <td class="mono" style="text-align:right; font-weight:600;"><?= rupiah($item['sub_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex; justify-content:flex-end; margin-top:20px;">
                    <div style="min-width:240px; font-size:0.9rem;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                            <span style="color:var(--text-muted);">Subtotal:</span>
                            <span class="mono"><?= rupiah($po['subtotal_nilai']) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                            <span style="color:var(--text-muted);">PPN (11%):</span>
                            <span class="mono"><?= rupiah($po['ppn']) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding-top:8px; border-top:1px dashed var(--border-light); font-weight:700; font-size:1.05rem;">
                            <span>Total Pengadaan:</span>
                            <span class="mono" style="color:var(--accent-primary);"><?= rupiah($po['total_nilai']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Tutup</button>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- ================= Modal Buat Pengadaan Baru ================= -->
<?php if ($canManage): ?>
<div class="modal-backdrop" id="modal-pengadaan-baru" role="dialog" aria-modal="true" aria-labelledby="modal-pengadaan-title">
    <div class="modal-dialog modal-lg">
        <form method="post" action="?action=pengadaan_simpan" id="form-pengadaan">
            <?= csrf_field() ?>

            <div class="modal-header">
                <div>
                    <span class="micro-label">Form Transaksi</span>
                    <h3 class="modal-title" id="modal-pengadaan-title">Buat Pengadaan Buku Baru</h3>
                </div>
                <button type="button" class="btn-ghost" data-close-modal style="min-height:auto; padding:4px;" aria-label="Tutup">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="po_vendor">Pilih Vendor / Penerbit <span class="req">*</span></label>
                    <select name="idvendor" id="po_vendor" class="form-control" required>
                        <option value="">-- Pilih Vendor Penerbit --</option>
                        <?php foreach ($daftarVendor as $v): ?>
                            <option value="<?= (int)$v['idvendor'] ?>"><?= e($v['nama_vendor']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-top:24px; margin-bottom:12px; display:flex; align-items:center; justify-content:space-between;">
                    <label class="micro-label" style="margin:0;">Daftar Buku Yang Dipesan</label>
                    <button type="button" class="btn btn-secondary btn-sm" id="btn-tambah-baris-po">
                        + Tambah Baris Buku
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="data-table" id="tabel-item-po">
                        <thead>
                            <tr>
                                <th>Buku</th>
                                <th style="width:120px;">Jumlah</th>
                                <th style="width:160px;">Harga Satuan (Rp)</th>
                                <th style="width:150px; text-align:right;">Subtotal</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="baris-item-po-wrap">
                            <tr class="po-item-row">
                                <td>
                                    <select name="items[0][idbarang]" class="form-control item-select-buku" required>
                                        <option value="">-- Pilih Buku --</option>
                                        <?php foreach ($daftarBuku as $b): ?>
                                            <option value="<?= (int)$b['idbarang'] ?>" data-harga="<?= (int)$b['harga'] ?>">
                                                <?= e($b['nama']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="items[0][jumlah]" class="form-control mono item-qty" min="1" value="10" required>
                                </td>
                                <td>
                                    <input type="number" name="items[0][harga_satuan]" class="form-control mono item-harga" min="0" step="500" value="50000" required>
                                </td>
                                <td class="mono item-subtotal" style="text-align:right; font-weight:600;">
                                    Rp 500.000
                                </td>
                                <td style="text-align:center;">
                                    <button type="button" class="btn-ghost btn-hapus-baris-po" style="color:var(--danger); padding:4px;" title="Hapus baris">&times;</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex; justify-content:flex-end; margin-top:20px;">
                    <div style="min-width:260px; font-size:0.9rem;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                            <span style="color:var(--text-muted);">Subtotal:</span>
                            <span class="mono" id="label-po-subtotal">Rp 0</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                            <span style="color:var(--text-muted);">PPN (11%):</span>
                            <span class="mono" id="label-po-ppn">Rp 0</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding-top:8px; border-top:1px dashed var(--border-light); font-weight:700; font-size:1.1rem;">
                            <span>Total Pesanan:</span>
                            <span class="mono" style="color:var(--accent-primary);" id="label-po-total">Rp 0</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Transaksi Pengadaan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let rowIndex = 1;
    const wrap = document.getElementById('baris-item-po-wrap');
    const btnTambah = document.getElementById('btn-tambah-baris-po');

    function hitungSemua() {
        let subtotal = 0;
        document.querySelectorAll('.po-item-row').forEach(row => {
            const qty = parseInt(row.querySelector('.item-qty').value || 0, 10);
            const harga = parseInt(row.querySelector('.item-harga').value || 0, 10);
            const sub = qty * harga;
            row.querySelector('.item-subtotal').textContent = 'Rp ' + sub.toLocaleString('id-ID');
            subtotal += sub;
        });
        const ppn = Math.round(subtotal * 0.11);
        const total = subtotal + ppn;

        document.getElementById('label-po-subtotal').textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
        document.getElementById('label-po-ppn').textContent = 'Rp ' + ppn.toLocaleString('id-ID');
        document.getElementById('label-po-total').textContent = 'Rp ' + total.toLocaleString('id-ID');
    }

    if (btnTambah && wrap) {
        btnTambah.addEventListener('click', () => {
            const template = wrap.querySelector('.po-item-row');
            if (!template) return;
            const newRow = template.cloneNode(true);
            newRow.querySelector('.item-select-buku').name = `items[${rowIndex}][idbarang]`;
            newRow.querySelector('.item-select-buku').value = '';
            newRow.querySelector('.item-qty').name = `items[${rowIndex}][jumlah]`;
            newRow.querySelector('.item-qty').value = '10';
            newRow.querySelector('.item-harga').name = `items[${rowIndex}][harga_satuan]`;
            newRow.querySelector('.item-harga').value = '50000';
            newRow.querySelector('.item-subtotal').textContent = 'Rp 500.000';
            wrap.appendChild(newRow);
            rowIndex++;
            pasangListener(newRow);
            hitungSemua();
        });
    }

    function pasangListener(row) {
        row.querySelectorAll('input, select').forEach(el => {
            el.addEventListener('input', hitungSemua);
            el.addEventListener('change', hitungSemua);
        });
        row.querySelector('.btn-hapus-baris-po').addEventListener('click', () => {
            if (document.querySelectorAll('.po-item-row').length > 1) {
                row.remove();
                hitungSemua();
            } else {
                alert('Minimal satu baris buku harus ada.');
            }
        });
    }

    document.querySelectorAll('.po-item-row').forEach(row => pasangListener(row));
    hitungSemua();
});
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/layout-footer.php'; ?>

