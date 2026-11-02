<?php
declare(strict_types=1);

/**
 * PustakaRapi — Halaman Data Buku
 * Membaca katalog dari View MySQL `v_daftar_buku`.
 */

auth_require_page('buku');

$q        = sanitize_input($_GET['q'] ?? '');
$kategori = sanitize_input($_GET['kategori'] ?? '');
$urut     = sanitize_input($_GET['urut'] ?? 'id');

// Ambil data katalog via Adapter
$katalogResult = BackendAdapter::getKatalogBuku($pdo, $q, $kategori, $urut);
$daftarBuku    = $katalogResult['items'];

// Hitung metrik ringkasan
$stmtMetrik = $pdo->query('
    SELECT 
        COUNT(*) AS total_judul,
        COUNT(DISTINCT jenis) AS total_kategori,
        COALESCE(SUM(harga), 0) AS total_nilai
    FROM barang
');
$metrik = $stmtMetrik->fetch();

// Hitung buku per kategori untuk filter chip
$hitungKategori = [];
foreach ($pdo->query('SELECT jenis, COUNT(*) AS jml FROM barang GROUP BY jenis') as $r) {
    $hitungKategori[$r['jenis']] = (int)$r['jml'];
}

// Ambil data referensi satuan untuk dropdown form
$daftarSatuan = $pdo->query('SELECT idsatuan, nama_satuan FROM satuan WHERE status = 1 ORDER BY nama_satuan ASC')->fetchAll();

$canManage = auth_can_manage('buku');

include dirname(__DIR__) . '/includes/layout-header.php';
?>

<!-- ================= Metrik Header ================= -->
<div class="stat-grid">
    <div class="stat-card">
        <div>
            <span class="stat-label">Total Judul Buku</span>
            <div class="stat-val"><?= format_angka($metrik['total_judul']) ?></div>
            <div class="stat-desc">Katalog terdaftar aktif</div>
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
            <span class="stat-label">Kategori / Genre</span>
            <div class="stat-val"><?= format_angka($metrik['total_kategori']) ?></div>
            <div class="stat-desc">Variasi bidang pustaka</div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>
            </svg>
        </div>
    </div>

    <div class="stat-card accent-stat">
        <div>
            <span class="stat-label">Nilai Kumulatif Harga</span>
            <div class="stat-val"><?= rupiah($metrik['total_nilai']) ?></div>
            <div class="stat-desc">Harga banderol katalog</div>
        </div>
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </div>
    </div>
</div>

<!-- ================= Card Konten Utama ================= -->
<div class="card">
    <div class="card-header">
        <div class="card-title-group">
            <h2>Katalog Buku</h2>
            <p class="card-subtitle">
                Sumber data: View <code>v_daftar_buku</code>. Operasi simpan & hapus ditargetkan ke tabel <code>barang</code>.
            </p>
        </div>
        <?php if ($canManage): ?>
            <button type="button" class="btn btn-primary" data-open-modal="modal-buku" data-item='{"idbarang":0,"nama":"","jenis":"F","idsatuan":<?= !empty($daftarSatuan[0]['idsatuan']) ? (int)$daftarSatuan[0]['idsatuan'] : 0 ?>,"harga":0,"status":1,"action_title":"Tambah Buku Baru"}'>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Tambah Buku
            </button>
        <?php endif; ?>
    </div>

    <!-- Filter & Pencarian Toolbar -->
    <form method="get" action="" class="filter-toolbar form-search-auto">
        <input type="hidden" name="page" value="buku">
        <?php if ($kategori !== ''): ?>
            <input type="hidden" name="kategori" value="<?= e($kategori) ?>">
        <?php endif; ?>

        <div class="search-box">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input type="search" name="q" class="form-control" placeholder="Cari judul buku atau ID..." value="<?= e($q) ?>" aria-label="Cari buku">
        </div>

        <div class="chip-group" role="group" aria-label="Filter Kategori">
            <a href="?page=buku<?= $q !== '' ? '&q=' . urlencode($q) : '' ?>&urut=<?= e($urut) ?>" 
               class="chip <?= $kategori === '' ? 'active' : '' ?>">
                Semua <span class="count"><?= (int)$metrik['total_judul'] ?></span>
            </a>
            <?php foreach (GENRE as $kode => $labelNama): ?>
                <?php $jml = $hitungKategori[$kode] ?? 0; ?>
                <a href="?page=buku&kategori=<?= $kode ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>&urut=<?= e($urut) ?>" 
                   class="chip <?= $kategori === $kode ? 'active' : '' ?>">
                    <?= e($labelNama) ?> <span class="count"><?= $jml ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div style="display:flex; align-items:center; gap:8px;">
            <label for="select-urut" class="micro-label" style="margin:0;">Urutkan:</label>
            <select name="urut" id="select-urut" class="form-control" style="width:auto; padding:6px 12px; border-radius:var(--radius-pill);" onchange="this.form.submit()">
                <option value="id" <?= $urut === 'id' ? 'selected' : '' ?>>ID Buku</option>
                <option value="judul" <?= $urut === 'judul' ? 'selected' : '' ?>>Judul A–Z</option>
                <option value="murah" <?= $urut === 'murah' ? 'selected' : '' ?>>Harga Termurah</option>
                <option value="mahal" <?= $urut === 'mahal' ? 'selected' : '' ?>>Harga Termahal</option>
            </select>
        </div>
    </form>

    <!-- Tabel Data Buku -->
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Judul Buku</th>
                    <th style="width: 140px;">Kategori</th>
                    <th style="width: 110px;">Satuan</th>
                    <th style="width: 150px;">Harga Jual</th>
                    <th style="width: 110px;">Status</th>
                    <?php if ($canManage): ?>
                        <th style="width: 130px; text-align:right;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftarBuku)): ?>
                    <tr>
                        <td colspan="<?= $canManage ? '7' : '6' ?>">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                    </svg>
                                </div>
                                <h3>Data Buku Tidak Ditemukan</h3>
                                <p>Tidak ada catatan buku yang sesuai dengan kriteria pencarian atau filter yang dipilih.</p>
                                <?php if ($q !== '' || $kategori !== ''): ?>
                                    <a href="?page=buku" class="btn btn-secondary btn-sm">Reset Pencarian</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftarBuku as $b): ?>
                        <?php
                        $rawEditData = json_encode([
                            'idbarang'     => (int)$b['id_buku'],
                            'nama'         => (string)$b['judul_buku'],
                            'jenis'        => (string)$b['kode_jenis'],
                            'harga'        => (int)$b['harga_jual'],
                            'status'       => (int)$b['kode_status'],
                            'action_title' => 'Ubah Buku: ' . $b['judul_buku'],
                        ], JSON_HEX_APOS | JSON_HEX_QUOT);
                        ?>
                        <tr>
                            <td class="mono" style="font-weight:600; color:var(--text-subtle);">#<?= (int)$b['id_buku'] ?></td>
                            <td style="font-weight:600; color:var(--text-main);"><?= e($b['judul_buku']) ?></td>
                            <td><?= render_badge_status((string)$b['kode_jenis'], 'genre') ?></td>
                            <td style="color:var(--text-muted);"><?= e($b['nama_satuan'] ?? '—') ?></td>
                            <td class="mono" style="font-weight:600;"><?= rupiah($b['harga_jual']) ?></td>
                            <td><?= render_badge_status((string)$b['kode_status'], 'aktif_nonaktif') ?></td>
                            <?php if ($canManage): ?>
                                <td>
                                    <div class="table-actions">
                                        <button type="button" class="btn btn-secondary btn-sm" 
                                                data-open-modal="modal-buku" 
                                                data-item='<?= e($rawEditData) ?>'>
                                            Ubah
                                        </button>
                                        <form method="post" action="?action=buku_hapus" data-confirm="Hapus permanen buku &quot;<?= e($b['judul_buku']) ?>&quot;?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="idbarang" value="<?= (int)$b['id_buku'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);" title="Hapus">
                                                Hapus
                                            </button>
                                        </form>
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

<!-- ================= Modal Form Tambah / Ubah Buku ================= -->
<?php if ($canManage): ?>
<div class="modal-backdrop" id="modal-buku" role="dialog" aria-modal="true" aria-labelledby="modal-buku-title">
    <div class="modal-dialog">
        <form method="post" action="?action=buku_simpan">
            <?= csrf_field() ?>
            <input type="hidden" name="idbarang" value="0">

            <div class="modal-header">
                <div>
                    <span class="micro-label">Katalog Buku</span>
                    <h3 class="modal-title" id="modal-buku-title">Tambah Buku</h3>
                </div>
                <button type="button" class="btn-ghost" data-close-modal style="min-height:auto; padding:4px;" aria-label="Tutup">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="buku_nama">Judul Buku <span class="req">*</span></label>
                    <input type="text" name="nama" id="buku_nama" class="form-control" required maxlength="45" placeholder="Contoh: Laskar Pelangi">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="buku_jenis">Kategori / Genre <span class="req">*</span></label>
                        <select name="jenis" id="buku_jenis" class="form-control" required>
                            <?php foreach (GENRE as $kode => $labelNama): ?>
                                <option value="<?= e($kode) ?>"><?= e($labelNama) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="buku_satuan">Satuan <span class="req">*</span></label>
                        <select name="idsatuan" id="buku_satuan" class="form-control" required>
                            <?php foreach ($daftarSatuan as $s): ?>
                                <option value="<?= (int)$s['idsatuan'] ?>"><?= e($s['nama_satuan']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="buku_harga">Harga Jual (Rp) <span class="req">*</span></label>
                        <input type="number" name="harga" id="buku_harga" class="form-control mono" min="0" step="500" required placeholder="85000">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="buku_status">Status Publikasi</label>
                        <select name="status" id="buku_status" class="form-control">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Buku</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/layout-footer.php'; ?>

