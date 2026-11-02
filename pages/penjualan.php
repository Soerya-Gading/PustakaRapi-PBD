<?php
declare(strict_types=1);

/**
 * PustakaRapi — Halaman Kasir / Penjualan (POS)
 */

auth_require_page('penjualan');

$canManage = auth_can_manage('penjualan');
$strukId   = (int)($_GET['struk'] ?? 0);

// Ambil riwayat penjualan terkini
$stmtHistori = $pdo->query('
    SELECT p.idpenjualan, p.created_at, p.subtotal_nilai, p.ppn, p.total_nilai,
           u.username AS kasir, m.persen AS margin_persen,
           COUNT(dp.iddetail_penjualan) AS total_item
    FROM penjualan p
    LEFT JOIN `user` u ON p.iduser = u.iduser
    LEFT JOIN margin_penjualan m ON p.idmargin_penjualan = m.idmargin_penjualan
    LEFT JOIN detail_penjualan dp ON p.idpenjualan = dp.penjualan_idpenjualan
    GROUP BY p.idpenjualan, p.created_at, p.subtotal_nilai, p.ppn, p.total_nilai, u.username, m.persen
    ORDER BY p.idpenjualan DESC
    LIMIT 20
');
$riwayatPenjualan = $stmtHistori->fetchAll();

// Ambil data struk jika diminta
$strukData = null;
$strukItems = [];
if ($strukId > 0) {
    $stmtStruk = $pdo->prepare('
        SELECT p.idpenjualan, p.created_at, p.subtotal_nilai, p.ppn, p.total_nilai,
               u.username AS kasir, m.persen AS margin_persen
        FROM penjualan p
        LEFT JOIN `user` u ON p.iduser = u.iduser
        LEFT JOIN margin_penjualan m ON p.idmargin_penjualan = m.idmargin_penjualan
        WHERE p.idpenjualan = ?
    ');
    $stmtStruk->execute([$strukId]);
    $strukData = $stmtStruk->fetch();

    if ($strukData) {
        $stmtStrukItems = $pdo->prepare('
            SELECT dp.jumlah, dp.harga_satuan, dp.subtotal, b.nama AS judul_buku, s.nama_satuan
            FROM detail_penjualan dp
            LEFT JOIN barang b ON dp.idbarang = b.idbarang
            LEFT JOIN satuan s ON b.idsatuan = s.idsatuan
            WHERE dp.penjualan_idpenjualan = ?
        ');
        $stmtStrukItems->execute([$strukId]);
        $strukItems = $stmtStrukItems->fetchAll();
    }
}

// Ambil margin penjualan yang aktif
$daftarMargin = $pdo->query('SELECT idmargin_penjualan, persen FROM margin_penjualan WHERE status = 1 ORDER BY persen ASC')->fetchAll();

// Ambil katalog buku beserta status stok aktual
$stmtKatalog = $pdo->query('
    SELECT b.idbarang, b.nama, b.harga, s.nama_satuan
    FROM barang b
    LEFT JOIN satuan s ON b.idsatuan = s.idsatuan
    WHERE b.status = 1
    ORDER BY b.nama ASC
');
$katalogBuku = [];
foreach ($stmtKatalog->fetchAll() as $row) {
    $stk = BackendAdapter::hitungStokBuku($pdo, (int)$row['idbarang']);
    $row['stok']     = $stk['stok'];
    $row['tercatat'] = $stk['tercatat'];
    $row['status_stok'] = $stk['status'];
    $katalogBuku[] = $row;
}

include dirname(__DIR__) . '/includes/layout-header.php';
?>

<!-- ================= Struk Kasir Modal ================= -->
<?php if ($strukData): ?>
<div class="modal-backdrop is-open" id="modal-struk-kasir" role="dialog" aria-modal="true">
    <div class="modal-dialog" style="max-width: 420px;">
        <div class="modal-header">
            <div>
                <span class="micro-label">Struk Resmi</span>
                <h3 class="modal-title">Struk Kasir #TRX-<?= (int)$strukData['idpenjualan'] ?></h3>
            </div>
            <a href="?page=penjualan" class="btn-ghost" style="padding:4px;" aria-label="Tutup">&times;</a>
        </div>
        <div class="modal-body">
            <div class="receipt-wrap" id="printable-receipt">
                <div class="receipt-header">
                    <div class="receipt-brand">PUSTAKARAPI</div>
                    <div style="font-size:0.75rem; color:#64748b;">Toko Buku & Persediaan Mandiri</div>
                    <div style="font-size:0.75rem; margin-top:6px;">
                        No: #TRX-<?= (int)$strukData['idpenjualan'] ?><br>
                        Waktu: <?= format_tanggal($strukData['created_at']) ?><br>
                        Kasir: <?= e($strukData['kasir']) ?>
                    </div>
                </div>

                <div style="margin-bottom:12px;">
                    <?php foreach ($strukItems as $si): ?>
                        <div style="margin-bottom:6px;">
                            <div style="font-weight:600;"><?= e($si['judul_buku']) ?></div>
                            <div class="receipt-row" style="color:#475569; font-size:0.78rem;">
                                <span><?= (int)$si['jumlah'] ?>x @ <?= rupiah($si['harga_satuan']) ?></span>
                                <span class="mono"><?= rupiah($si['subtotal']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="receipt-divider"></div>

                <div class="receipt-row">
                    <span>Subtotal:</span>
                    <span class="mono"><?= rupiah($strukData['subtotal_nilai']) ?></span>
                </div>
                <div class="receipt-row">
                    <span>PPN (11%):</span>
                    <span class="mono"><?= rupiah($strukData['ppn']) ?></span>
                </div>
                <div class="receipt-divider"></div>
                <div class="receipt-row bold" style="font-size:0.95rem; color:#0f172a;">
                    <span>TOTAL:</span>
                    <span class="mono"><?= rupiah($strukData['total_nilai']) ?></span>
                </div>

                <div class="receipt-divider"></div>
                <div style="text-align:center; font-size:0.75rem; color:#64748b; margin-top:8px;">
                    Terima kasih telah berbelanja di PustakaRapi!<br>
                    Barang yang sudah dibeli tidak dapat ditukar kecuali melampirkan bukti retur.
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <a href="?page=penjualan" class="btn btn-secondary">Tutup</a>
            <button type="button" class="btn btn-primary" onclick="printReceipt('printable-receipt')">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
                </svg>
                Cetak Struk
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ================= Terminal POS / Kasir ================= -->
<?php if ($canManage): ?>
<div style="display:grid; grid-template-columns: 1fr 380px; gap: 24px; margin-bottom: 32px;" class="pos-layout">

    <!-- Kolom Kiri: Katalog Buku & Keranjang Belanja -->
    <div style="display:flex; flex-direction:column; gap:20px;">
        <div class="card">
            <div class="card-header" style="margin-bottom:14px;">
                <div class="card-title-group">
                    <h2>Pilih Buku Kasir</h2>
                    <p class="card-subtitle">Pilih buku dari katalog untuk dimasukkan ke keranjang kasir.</p>
                </div>
            </div>

            <div class="search-box" style="margin-bottom:14px; width:100%;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="search" id="pos-search-input" class="form-control" placeholder="Ketik untuk mencari judul buku...">
            </div>

            <div class="table-responsive" style="max-height: 260px; overflow-y:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Judul Buku</th>
                            <th style="width:130px; text-align:right;">Harga Jual</th>
                            <th style="width:140px; text-align:center;">Status Stok</th>
                            <th style="width:90px; text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="pos-book-catalog-tbody">
                        <?php foreach ($katalogBuku as $b): ?>
                            <?php
                            $isOutOfStock = !$b['tercatat'] || $b['stok'] <= 0;
                            $stokLabel = $b['tercatat'] ? ($b['stok'] . ' ' . $b['nama_satuan']) : 'Stok belum tercatat';
                            ?>
                            <tr class="pos-book-item" data-title="<?= strtolower(e($b['nama'])) ?>">
                                <td style="font-weight:600;"><?= e($b['nama']) ?></td>
                                <td class="mono" style="text-align:right; font-weight:600;"><?= rupiah($b['harga']) ?></td>
                                <td style="text-align:center;">
                                    <?php if (!$b['tercatat']): ?>
                                        <span class="badge badge-muted">Stok belum tercatat</span>
                                    <?php elseif ($b['stok'] <= 0): ?>
                                        <span class="badge badge-danger">Habis (0)</span>
                                    <?php elseif ($b['stok'] <= 5): ?>
                                        <span class="badge badge-warning">Sisa <?= $b['stok'] ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Stok: <?= $b['stok'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <button type="button" class="btn btn-secondary btn-sm btn-pos-add"
                                            <?= $isOutOfStock ? 'disabled' : '' ?>
                                            data-id="<?= (int)$b['idbarang'] ?>"
                                            data-nama="<?= e($b['nama']) ?>"
                                            data-harga="<?= (int)$b['harga'] ?>"
                                            data-stok="<?= (int)$b['stok'] ?>"
                                            data-tercatat="<?= $b['tercatat'] ? '1' : '0' ?>">
                                        + Pilih
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabel Keranjang Kasir -->
        <div class="card">
            <div class="card-header">
                <div class="card-title-group">
                    <h2>Keranjang Kasir</h2>
                    <p class="card-subtitle">Daftar item buku yang akan dibeli pelanggan.</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" id="btn-kosongkan-keranjang" style="color:var(--danger);">
                    Kosongkan
                </button>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Buku</th>
                            <th style="width:130px; text-align:right;">Harga</th>
                            <th style="width:110px; text-align:center;">Jumlah</th>
                            <th style="width:140px; text-align:right;">Subtotal</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="pos-cart-tbody">
                        <tr id="pos-cart-empty">
                            <td colspan="5" style="text-align:center; padding:36px; color:var(--text-muted);">
                                Keranjang masih kosong. Pilih buku dari katalog di atas.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Ringkasan Checkout & Pembayaran -->
    <div>
        <div class="card" style="position:sticky; top:88px;">
            <form method="post" action="?action=penjualan_simpan" id="form-penjualan">
                <?= csrf_field() ?>
                <div id="hidden-cart-inputs"></div>

                <div class="card-title-group" style="margin-bottom:20px;">
                    <h3>Ringkasan Pembayaran</h3>
                    <p class="card-subtitle">Kasir: <strong><?= e(auth_username()) ?></strong></p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pos_margin">Margin Keuntungan Penjualan <span class="req">*</span></label>
                    <select name="idmargin_penjualan" id="pos_margin" class="form-control" required>
                        <?php foreach ($daftarMargin as $m): ?>
                            <option value="<?= (int)$m['idmargin_penjualan'] ?>">
                                Margin <?= (float)$m['persen'] ?>%
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="background-color:var(--bg-surface-subtle); border-radius:var(--radius-md); padding:16px; margin:20px 0;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem;">
                        <span style="color:var(--text-muted);">Subtotal Barang:</span>
                        <span class="mono" id="pos-label-subtotal">Rp 0</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.9rem;">
                        <span style="color:var(--text-muted);">PPN (11%):</span>
                        <span class="mono" id="pos-label-ppn">Rp 0</span>
                    </div>
                    <div style="border-top:1px dashed var(--border-light); padding-top:12px; margin-top:8px; display:flex; justify-content:space-between; align-items:baseline;">
                        <span style="font-weight:700; font-size:1.05rem;">Total Tagihan:</span>
                        <span class="mono" id="pos-label-grandtotal" style="font-size:1.45rem; font-weight:800; color:var(--accent-primary);">Rp 0</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" id="btn-submit-penjualan" style="width:100%; min-height:48px;" disabled>
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Proses Transaksi & Cetak Struk
                </button>
            </form>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const cart = new Map();
    const cartTbody = document.getElementById('pos-cart-tbody');
    const emptyRow = document.getElementById('pos-cart-empty');
    const hiddenInputsWrap = document.getElementById('hidden-cart-inputs');
    const submitBtn = document.getElementById('btn-submit-penjualan');
    const searchInput = document.getElementById('pos-search-input');

    // Filter katalog real-time
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const kw = searchInput.value.toLowerCase().trim();
            document.querySelectorAll('.pos-book-item').forEach(tr => {
                const title = tr.getAttribute('data-title') || '';
                tr.style.display = title.includes(kw) ? '' : 'none';
            });
        });
    }

    // Tombol Pilih Buku
    document.querySelectorAll('.btn-pos-add').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.dataset.id, 10);
            const nama = btn.dataset.nama;
            const harga = parseInt(btn.dataset.harga, 10);
            const maxStok = parseInt(btn.dataset.stok, 10);
            const tercatat = btn.dataset.tercatat === '1';

            if (cart.has(id)) {
                const item = cart.get(id);
                if (tercatat && item.qty + 1 > maxStok) {
                    alert(`Stok buku "${nama}" tidak mencukupi (tersedia: ${maxStok}).`);
                    return;
                }
                item.qty++;
            } else {
                if (tercatat && maxStok < 1) {
                    alert(`Stok buku "${nama}" habis.`);
                    return;
                }
                cart.set(id, { id, nama, harga, qty: 1, maxStok, tercatat });
            }
            renderCart();
        });
    });

    function renderCart() {
        if (cart.size === 0) {
            cartTbody.innerHTML = '';
            cartTbody.appendChild(emptyRow);
            hiddenInputsWrap.innerHTML = '';
            submitBtn.disabled = true;
            document.getElementById('pos-label-subtotal').textContent = 'Rp 0';
            document.getElementById('pos-label-ppn').textContent = 'Rp 0';
            document.getElementById('pos-label-grandtotal').textContent = 'Rp 0';
            return;
        }

        cartTbody.innerHTML = '';
        hiddenInputsWrap.innerHTML = '';
        submitBtn.disabled = false;

        let subtotal = 0;
        let index = 0;

        cart.forEach((item, id) => {
            const sub = item.qty * item.harga;
            subtotal += sub;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="font-weight:600;">${item.nama}</td>
                <td class="mono" style="text-align:right;">Rp ${item.harga.toLocaleString('id-ID')}</td>
                <td style="text-align:center;">
                    <input type="number" class="form-control mono cart-qty-input" 
                           style="width:70px; text-align:center; padding:4px;" 
                           min="1" ${item.tercatat ? `max="${item.maxStok}"` : ''} value="${item.qty}">
                </td>
                <td class="mono" style="text-align:right; font-weight:700;">Rp ${sub.toLocaleString('id-ID')}</td>
                <td style="text-align:center;">
                    <button type="button" class="btn-ghost" style="color:var(--danger); padding:4px;" title="Hapus">&times;</button>
                </td>
            `;

            // Qty change listener
            const qtyInput = tr.querySelector('.cart-qty-input');
            qtyInput.addEventListener('input', () => {
                let val = parseInt(qtyInput.value || 0, 10);
                if (item.tercatat && val > item.maxStok) {
                    alert(`Stok maksimal: ${item.maxStok}`);
                    val = item.maxStok;
                    qtyInput.value = val;
                }
                if (val < 1) val = 1;
                item.qty = val;
                renderCart();
            });

            // Remove button
            tr.querySelector('button').addEventListener('click', () => {
                cart.delete(id);
                renderCart();
            });

            cartTbody.appendChild(tr);

            // Hidden inputs for form submit
            hiddenInputsWrap.innerHTML += `
                <input type="hidden" name="items[${index}][idbarang]" value="${item.id}">
                <input type="hidden" name="items[${index}][jumlah]" value="${item.qty}">
                <input type="hidden" name="items[${index}][harga_satuan]" value="${item.harga}">
            `;
            index++;
        });

        const ppn = Math.round(subtotal * 0.11);
        const total = subtotal + ppn;

        document.getElementById('pos-label-subtotal').textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
        document.getElementById('pos-label-ppn').textContent = 'Rp ' + ppn.toLocaleString('id-ID');
        document.getElementById('pos-label-grandtotal').textContent = 'Rp ' + total.toLocaleString('id-ID');
    }

    document.getElementById('btn-kosongkan-keranjang').addEventListener('click', () => {
        cart.clear();
        renderCart();
    });
});
</script>
<?php endif; ?>

<!-- ================= Riwayat Penjualan ================= -->
<div class="card">
    <div class="card-header">
        <div class="card-title-group">
            <h2>Riwayat Transaksi Penjualan Terkini</h2>
            <p class="card-subtitle">Daftar struk transaksi kasir yang tercatat.</p>
        </div>
        <a href="?page=laporan-penjualan" class="btn btn-secondary btn-sm">Lihat Laporan Penjualan Lengkap</a>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">No. Struk</th>
                    <th>Waktu Transaksi</th>
                    <th>Kasir</th>
                    <th>Margin</th>
                    <th style="text-align:center;">Total Buku</th>
                    <th style="text-align:right;">Subtotal</th>
                    <th style="text-align:right;">PPN (11%)</th>
                    <th style="text-align:right;">Total Transaksi</th>
                    <th style="width: 100px; text-align:right;">Struk</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($riwayatPenjualan)): ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <h3>Belum Ada Transaksi Kasir</h3>
                                <p>Transaksi penjualan yang baru saja diselesaikan akan muncul pada tabel riwayat ini.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($riwayatPenjualan as $rp): ?>
                        <tr>
                            <td class="mono" style="font-weight:700; color:var(--text-subtle);">#TRX-<?= (int)$rp['idpenjualan'] ?></td>
                            <td style="font-size:0.86rem;"><?= format_tanggal($rp['created_at']) ?></td>
                            <td style="font-weight:600;"><?= e($rp['kasir']) ?></td>
                            <td><span class="badge badge-accent"><?= (float)$rp['margin_persen'] ?>%</span></td>
                            <td class="mono" style="text-align:center;"><?= (int)$rp['total_item'] ?> Item</td>
                            <td class="mono" style="text-align:right;"><?= rupiah($rp['subtotal_nilai']) ?></td>
                            <td class="mono" style="text-align:right;"><?= rupiah($rp['ppn']) ?></td>
                            <td class="mono" style="text-align:right; font-weight:700; color:var(--accent-primary);">
                                <?= rupiah($rp['total_nilai']) ?>
                            </td>
                            <td style="text-align:right;">
                                <a href="?page=penjualan&struk=<?= (int)$rp['idpenjualan'] ?>" class="btn btn-secondary btn-sm">
                                    Struk
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/layout-footer.php'; ?>

