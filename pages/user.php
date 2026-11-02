<?php
declare(strict_types=1);

/**
 * PustakaRapi — Halaman Manajemen Pengguna
 */

auth_require_page('user');

$stmt = $pdo->query('
    SELECT u.iduser, u.username, u.idrole, r.nama_role
    FROM `user` u
    LEFT JOIN role r ON u.idrole = r.idrole
    ORDER BY u.iduser ASC
');
$daftarUser = $stmt->fetchAll();

$daftarRole = $pdo->query('SELECT idrole, nama_role FROM role ORDER BY idrole ASC')->fetchAll();

include dirname(__DIR__) . '/includes/layout-header.php';
?>

<div class="card">
    <div class="card-header">
        <div class="card-title-group">
            <h2>Daftar Akun Pengguna</h2>
            <p class="card-subtitle">
                Pengelolaan akun dan pemberian role akses. Password diamankan menggunakan algoritma enkripsi satu arah Bcrypt.
            </p>
        </div>
        <button type="button" class="btn btn-primary" data-open-modal="modal-user" data-item='{"iduser":0,"username":"","idrole":<?= !empty($daftarRole[0]['idrole']) ? (int)$daftarRole[0]['idrole'] : 0 ?>,"action_title":"Tambah Pengguna Baru"}'>
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Tambah Pengguna
        </button>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ID</th>
                    <th>Username</th>
                    <th style="width: 180px;">Role Wewenang</th>
                    <th style="width: 160px; text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daftarUser as $u): ?>
                    <?php
                    $isSelf = (int)$u['iduser'] === auth_id();
                    $editData = json_encode([
                        'iduser'       => (int)$u['iduser'],
                        'username'     => (string)$u['username'],
                        'idrole'       => (int)$u['idrole'],
                        'action_title' => 'Ubah Akun: ' . $u['username'],
                    ], JSON_HEX_APOS | JSON_HEX_QUOT);
                    ?>
                    <tr>
                        <td class="mono" style="font-weight:600; color:var(--text-subtle);">#<?= (int)$u['iduser'] ?></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="user-avatar" style="width:32px; height:32px; font-size:0.85rem;">
                                    <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                </div>
                                <span style="font-weight:600;"><?= e($u['username']) ?></span>
                                <?php if ($isSelf): ?>
                                    <span class="badge badge-accent" style="font-size:0.65rem;">Akun Anda</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-info"><?= e($u['nama_role'] ?? 'Tidak Terdefinisi') ?></span>
                        </td>
                        <td>
                            <div class="table-actions">
                                <button type="button" class="btn btn-secondary btn-sm"
                                        data-open-modal="modal-user"
                                        data-item='<?= e($editData) ?>'>
                                    Ubah
                                </button>
                                <?php if (!$isSelf): ?>
                                    <form method="post" action="?action=user_hapus" data-confirm="Hapus akun pengguna &quot;<?= e($u['username']) ?>&quot;?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="iduser" value="<?= (int)$u['iduser'] ?>">
                                        <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= Modal Form User ================= -->
<div class="modal-backdrop" id="modal-user" role="dialog" aria-modal="true" aria-labelledby="modal-user-title">
    <div class="modal-dialog">
        <form method="post" action="?action=user_simpan">
            <?= csrf_field() ?>
            <input type="hidden" name="iduser" value="0">

            <div class="modal-header">
                <div>
                    <span class="micro-label">Akun Sistem</span>
                    <h3 class="modal-title" id="modal-user-title">Tambah Pengguna</h3>
                </div>
                <button type="button" class="btn-ghost" data-close-modal style="min-height:auto; padding:4px;" aria-label="Tutup">&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="user_username">Username <span class="req">*</span></label>
                    <input type="text" name="username" id="user_username" class="form-control" required maxlength="45" placeholder="Contoh: staff_gudang">
                </div>

                <div class="form-group">
                    <label class="form-label" for="user_role">Role Wewenang <span class="req">*</span></label>
                    <select name="idrole" id="user_role" class="form-control" required>
                        <?php foreach ($daftarRole as $r): ?>
                            <option value="<?= (int)$r['idrole'] ?>"><?= e($r['nama_role']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="user_password">Password</label>
                    <div class="password-field-wrap">
                        <input type="password" name="password" id="user_password" class="form-control" placeholder="Minimal 6 karakter (kosongkan jika tidak diubah)">
                        <button type="button" class="btn-toggle-password" aria-label="Lihat Password">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <small class="form-help">Saat mengubah pengguna lama, kosongkan bidang password jika tidak ingin mengganti kata sandi.</small>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Pengguna</button>
            </div>
        </form>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/layout-footer.php'; ?>

