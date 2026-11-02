<?php
declare(strict_types=1);

/**
 * PustakaRapi — Authentication & RBAC Guard
 */

if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

function auth_check(): bool
{
    return !empty($_SESSION['auth_user']['iduser']);
}

function auth_user(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function auth_id(): ?int
{
    return isset($_SESSION['auth_user']['iduser']) ? (int)$_SESSION['auth_user']['iduser'] : null;
}

function auth_username(): string
{
    return (string)($_SESSION['auth_user']['username'] ?? 'Tamu');
}

function auth_role_id(): ?int
{
    return isset($_SESSION['auth_user']['idrole']) ? (int)$_SESSION['auth_user']['idrole'] : null;
}

function auth_role_name(): string
{
    return (string)($_SESSION['auth_user']['nama_role'] ?? 'Pengunjung');
}

/**
 * Aturan hak akses modul berdasarkan nama role:
 * 1: Administrator -> Semua modul (CRUD)
 * 2: Kasir -> dashboard, penjualan, laporan-penjualan, buku (view)
 * 3: Gudang -> dashboard, penerimaan, kartu-stok, retur, buku (view)
 * 4: Purchasing -> dashboard, pengadaan, penerimaan, vendor, satuan, buku (view)
 * 5: Supervisor -> dashboard, laporan-penjualan, kartu-stok, pengadaan (view), penerimaan (view), penjualan (view), margin (view), retur (view)
 */
function auth_has_permission(string $page): bool
{
    if (!auth_check()) {
        return false;
    }

    $role = auth_role_name();
    if ($role === 'Administrator') {
        return true;
    }

    $permissions = [
        'Kasir' => [
            'dashboard', 'penjualan', 'laporan-penjualan', 'buku'
        ],
        'Gudang' => [
            'dashboard', 'penerimaan', 'kartu-stok', 'retur', 'buku'
        ],
        'Purchasing' => [
            'dashboard', 'pengadaan', 'penerimaan', 'vendor', 'satuan', 'buku'
        ],
        'Supervisor' => [
            'dashboard', 'laporan-penjualan', 'kartu-stok', 'pengadaan', 'penerimaan', 'penjualan', 'retur', 'margin', 'buku'
        ],
    ];

    $allowedPages = $permissions[$role] ?? [];
    return in_array($page, $allowedPages, true);
}

/**
 * Menentukan apakah user berhak mengubah data (Create/Update/Delete) pada modul tertentu
 */
function auth_can_manage(string $page): bool
{
    if (!auth_check()) {
        return false;
    }

    $role = auth_role_name();
    if ($role === 'Administrator') {
        return true;
    }

    $writePermissions = [
        'Kasir' => ['penjualan'],
        'Gudang' => ['penerimaan', 'retur'],
        'Purchasing' => ['pengadaan', 'penerimaan', 'vendor', 'satuan'],
        'Supervisor' => ['margin'],
    ];

    $allowed = $writePermissions[$role] ?? [];
    return in_array($page, $allowed, true);
}

function auth_require_page(string $page): void
{
    if (!auth_check()) {
        flash('warning', 'Silakan masuk terlebih dahulu untuk mengakses sistem.');
        redirect('?page=login');
    }

    if (!auth_has_permission($page)) {
        http_response_code(403);
        include __DIR__ . '/layout-header.php';
        ?>
        <div class="content-container">
            <div class="error-forbidden-card">
                <div class="error-forbidden-icon">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                    </svg>
                </div>
                <h2>Akses Ditolak (403 Forbidden)</h2>
                <p>Role Anda saat ini (<strong><?= e(auth_role_name()) ?></strong>) tidak memiliki otorisasi untuk mengakses halaman <strong><?= e($page) ?></strong>.</p>
                <div style="margin-top: 24px;">
                    <a href="?page=dashboard" class="btn btn-primary">Kembali ke Dashboard</a>
                </div>
            </div>
        </div>
        <?php
        include __DIR__ . '/layout-footer.php';
        exit;
    }
}

function auth_login(PDO $pdo, string $username, string $password): array
{
    $username = trim($username);
    if ($username === '' || $password === '') {
        return ['ok' => false, 'pesan' => 'Username dan password wajib diisi.'];
    }

    $stmt = $pdo->prepare('
        SELECT u.iduser, u.username, u.password, u.idrole, r.nama_role 
        FROM `user` u 
        LEFT JOIN `role` r ON u.idrole = r.idrole 
        WHERE u.username = ?
        LIMIT 1
    ');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['ok' => false, 'pesan' => 'Username tidak ditemukan.'];
    }

    if (!password_verify($password, $user['password'])) {
        return ['ok' => false, 'pesan' => 'Password tidak cocok.'];
    }

    // Set Session
    $_SESSION['auth_user'] = [
        'iduser'    => (int)$user['iduser'],
        'username'  => (string)$user['username'],
        'idrole'    => (int)$user['idrole'],
        'nama_role' => (string)($user['nama_role'] ?? 'Pengguna'),
        'login_at'  => date('Y-m-d H:i:s'),
    ];

    // Regenerate session id to protect against session fixation
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        session_regenerate_id(true);
    }

    return ['ok' => true, 'user' => $_SESSION['auth_user']];
}

function auth_logout(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    unset($_SESSION['auth_user']);
    session_destroy();
}
