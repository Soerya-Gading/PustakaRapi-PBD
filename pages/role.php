<?php
declare(strict_types=1);

/**
 * PustakaRapi — Halaman Manajemen Role
 */

auth_require_page('role');

$stmt = $pdo->query('
    SELECT r.idrole, r.nama_role, COUNT(u.iduser) AS total_user
    FROM role r
    LEFT JOIN `user` u ON r.idrole = u.idrole
    GROUP BY r.idrole, r.nama_role
    ORDER BY r.idrole ASC
');
$daftarRole = $stmt->fetchAll();

include dirname(__DIR__) . '/includes/layout-header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title-group">
            <h2>Role & Tingkat Otorisasi</h2>
            <p class="card-subtitle">
                Hak akses menentukan pembagian wewenang peran: Administrator, Kasir, Gudang, Purchasing, dan Supervisor.
            </p>
        </div>
        <button type="button" class="btn btn-primary" data-open-modal="modal-role" data-item='{"idrole":0,"nama_role":"","action_title":"Tambah Role Baru"}'>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Tambah Role
        </button>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Nama Role</th>
                    <th style="width: 180px;">Pengguna Terkait</th>
                    <th style="width: 140px; text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daftarRole as $r): ?>
                    <?php
                    $editData = json_encode([
                        'idrole'       => (int)$r['idrole'],
                        'nama_role'    => (string)$r['nama_role'],
                        'action_title' => 'Ubah Role: ' . $r['nama_role'],
                    ], JSON_HEX_APOS | JSON_HEX_QUOT);
                    $isTerpakai = (int)$r['total_user'] > 0;
                    ?>
                    <tr>
                        <td class="mono" style="font-weight:600; color:var(--text-subtle);">#<?= (int)$r['idrole'] ?></td>
                        <td style="font-weight:600; color:var(--text-main);"><?= e($r['nama_role']) ?></td>
                        <td>
                            <span class="badge <?= $isTerpakai ? 'badge-info' : 'badge-muted' ?>">
                                <?= (int)$r['total_user'] ?> Akun Terdaftar
                            </span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <button type="button" class="btn btn-secondary btn-sm"
                                        data-open-modal="modal-role"
                                        data-item='<?= e($editData) ?>'>
                                    Ubah
                                </button>
                                <?php if (!$isTerpakai): ?>
                                    <form method="post" action="?action=role_hapus" data-confirm="Hapus role &quot;<?= e($r['nama_role']) ?>&quot;?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="idrole" value="<?= (int)$r['idrole'] ?>">
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Hapus</button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-ghost btn-sm" style="color:var(--text-subtle); cursor:not-allowed;" title="Role sedang digunakan oleh akun pengguna">Terkunci</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= Modal Form Role ================= -->
<div class="modal-backdrop" id="modal-role" role="dialog" aria-modal="true" aria-labelledby="modal-role-title">
    <div class="modal-dialog">
        <form method="post" action="?action=role_simpan">
            <?= csrf_field() ?>
            <input type="hidden" name="idrole" value="0">

            <div class="modal-header">
                <div>
                    <span class="micro-label">Otorisasi Sistem</span>
                    <h3 class="modal-title" id="modal-role-title">Tambah Role</h3>
                </div>
                <button type="button" class="btn-ghost" data-close-modal style="min-height:auto; padding:4px;" aria-label="Tutup">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="role_nama">Nama Role <span class="req">*</span></label>
                    <input type="text" name="nama_role" id="role_nama" class="form-control" required maxlength="100" placeholder="Contoh: Manajer Operasional">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Role</button>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/layout-footer.php'; ?>

