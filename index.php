<?php
function e($teks) {
    return htmlspecialchars((string)$teks, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

$page = (isset($_GET['page']) && $_GET['page'] === 'role') ? 'role' : 'buku';

$buku = [];
$role = [];
$satuan = [];
$error = '';

$config = require __DIR__ . '/config.php';
if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_replace($config, require __DIR__ . '/config.local.php');
}

try {
    $dsn = 'mysql:host=' . $config['host'] . ';port=' . (int)$config['port'] . ';dbname=' . $config['database'] . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    $buku = $pdo->query('SELECT id_buku, judul_buku, nama_satuan, harga_pengadaan, kode_status FROM v_inventaris_buku ORDER BY id_buku')->fetchAll();
    $role = $pdo->query('SELECT idrole, nama_role FROM role ORDER BY idrole')->fetchAll();
    $satuan = $pdo->query('SELECT idsatuan, nama_satuan FROM satuan ORDER BY idsatuan')->fetchAll();
} catch (PDOException $ex) {
    $error = 'Gagal terhubung ke database. Pastikan MySQL/XAMPP menyala dan pengaturan di config.php sudah benar.';
}

$jumlahBuku = $error ? '-' : count($buku);
$jumlahRole = $error ? '-' : count($role);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $page === 'role' ? 'Role' : 'Data Buku' ?> - PustakaRapi</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/app.js" defer></script>
</head>
<body>

<aside class="sidebar">
    <div class="brand">PustakaRapi<small>Inventaris Buku</small></div>
    <nav>
        <a href="?page=buku" class="<?= $page === 'buku' ? 'active' : '' ?>">Data Buku</a>
        <a href="?page=role" class="<?= $page === 'role' ? 'active' : '' ?>">Role</a>
    </nav>
</aside>

<div class="content">
    <header class="topbar">
        <span class="status <?= $error ? 'off' : 'on' ?>">
            <?= $error ? '🔴 Database Terputus' : '🟢 Database Terhubung' ?>
        </span>
        
        <div class="user-info">
            👤 <strong>Soerya</strong> <small>(Role: Admin)</small>
        </div>
    </header>

    <main>
        <h1><?= $page === 'role' ? 'Data Role' : 'Data Buku' ?></h1>

        <?php if ($error): ?>
            <div class="pesan pesan-error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="ringkasan">
            <div><span>Jumlah buku</span><strong><?= e($jumlahBuku) ?></strong></div>
            <div><span>Jumlah role</span><strong><?= e($jumlahRole) ?></strong></div>
        </div>

        <?php if ($page === 'buku'): ?>
        <section class="kotak">
            <div class="kotak-atas">
                <div>
                    <h2>Daftar Buku</h2>
                </div>
                <button type="button" class="btn btn-utama" data-aksi="tambah">+ Tambah Buku</button>
            </div>

            <div class="alat">
                <input type="search" id="cari" placeholder="Cari buku...">
                <select id="urut">
                    <option value="id">Urut ID</option>
                    <option value="judul">Urut Judul A-Z</option>
                    <option value="harga">Urut Harga</option>
                </select>
            </div>

            <div class="tabel-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Judul Buku</th>
                            <th>Satuan</th>
                            <th>Harga</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="isi-tabel">
                        <?php foreach ($buku as $b): ?>
                        <tr class="baris" data-id="<?= e($b['id_buku']) ?>" data-judul="<?= e($b['judul_buku']) ?>" data-satuan="<?= e($b['nama_satuan']) ?>" data-harga="<?= e($b['harga_pengadaan']) ?>" data-status="<?= e($b['kode_status']) ?>">
                            <td><?= e($b['id_buku']) ?></td>
                            <td><?= e($b['judul_buku']) ?></td>
                            <td><?= e($b['nama_satuan']) ?></td>
                            <td><?= e(rupiah($b['harga_pengadaan'])) ?></td>
                            <td><?= e($b['kode_status']) ?></td>
                            <td class="aksi">
                                <button type="button" class="btn-kecil" data-aksi="ubah">Ubah</button>
                                <button type="button" class="btn-kecil btn-hapus" data-aksi="hapus">Hapus</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="baris-kosong" <?= $buku ? 'hidden' : '' ?>>
                            <td colspan="6"><?= $error ? 'Data gagal dimuat.' : 'Belum ada data buku.' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <?php else: ?>
        <section class="kotak">
            <div class="kotak-atas">
                <div>
                    <h2>Daftar Role</h2>
                </div>
            </div>
            <div class="tabel-wrap">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Nama Role</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($role as $r): ?>
                        <tr><td><?= e($r['idrole']) ?></td><td><?= e($r['nama_role']) ?></td></tr>
                        <?php endforeach; ?>
                        <?php if (!$role): ?>
                        <tr><td colspan="2"><?= $error ? 'Data gagal dimuat.' : 'Belum ada data role.' ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </main>
</div>

<!-- Modal Form CUD -->
<dialog id="dialog-form">
    <form method="dialog" id="form-buku">
        <h2 id="judul-form">Tambah Buku</h2>
        <label>Judul buku
            <input type="text" id="f-judul" required>
        </label>
        <label>Satuan
            <select id="f-satuan">
                <?php foreach ($satuan as $s): ?>
                    <option value="<?= e($s['idsatuan']) ?>"><?= e($s['nama_satuan']) ?></option>
                <?php endforeach; ?>
                <?php if (!$satuan): ?><option value="">-</option><?php endif; ?>
            </select>
        </label>
        <label>Harga
            <input type="number" id="f-harga" min="0">
        </label>
        <label>Status
            <input type="number" id="f-status" min="0" max="9" value="1">
        </label>
        <div class="dialog-tombol">
            <button type="button" class="btn" data-tutup>Batal</button>
            <button type="submit" class="btn btn-utama">Simpan</button>
        </div>
    </form>
</dialog>

<dialog id="dialog-hapus">
    <form method="dialog" id="form-hapus">
        <h2>Hapus Buku</h2>
        <p id="teks-hapus"></p>
        <div class="dialog-tombol">
            <button type="button" class="btn" data-tutup>Batal</button>
            <button type="submit" class="btn btn-bahaya">Hapus</button>
        </div>
    </form>
</dialog>

<div id="toast" hidden></div>

</body>
</html>