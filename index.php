<?php
declare(strict_types=1);

/**
 * PustakaRapi — Front Controller & Central Router
 * Mengatur routing, proteksi CSRF, sesi pengguna, dan RBAC server-side.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 1. Inisialisasi Dependensi Inti
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/adapter.php';

// 2. Hubungkan ke Database MySQL
try {
    $pdo = get_db_connection();
} catch (Exception $ex) {
    // Tampilkan layar kegagalan database yang ramah pengguna
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Offline · PustakaRapi</title>
        <link rel="stylesheet" href="public/css/app.css">
    </head>
    <body style="background:#f1f5f9; display:flex; align-items:center; justify-content:center; min-height:100vh;">
        <div class="card" style="max-width:520px; text-align:center; padding:48px 32px;">
            <div style="width:64px; height:64px; border-radius:50%; background:var(--danger-soft); color:var(--danger); display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2">
                    <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                    <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                    <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                </svg>
            </div>
            <h2>Layanan Database Tidak Terjangkau</h2>
            <p style="color:var(--text-muted); font-size:0.95rem; margin-top:8px;">
                Aplikasi tidak dapat terhubung ke MySQL pada port 3306. Pastikan Apache dan MySQL pada Laragon sudah dijalankan.
            </p>
            <div style="margin-top:24px;">
                <a href="" class="btn btn-primary">Muat Ulang Halaman</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 3. Dispatch Aksi (POST Requests)
$action = sanitize_input($_GET['action'] ?? ($_POST['action'] ?? ''));

if ($action !== '') {
    if (!csrf_verify()) {
        flash('gagal', 'Validasi token keamanan (CSRF) kedaluwarsa. Silakan muat ulang formulir.');
        $referer = $_SERVER['HTTP_REFERER'] ?? '?page=dashboard';
        redirect($referer);
    }

    $actionFile = __DIR__ . '/actions/' . $action . '.php';
    if (is_file($actionFile)) {
        require $actionFile;
        exit;
    } else {
        flash('gagal', 'Aksi yang diminta tidak dikenali oleh sistem.');
        redirect('?page=dashboard');
    }
}

// 4. Dispatch Halaman (GET Requests)
$page = sanitize_input($_GET['page'] ?? '');

// Tangani route khusus login / logout
if ($page === 'logout') {
    auth_logout();
    flash('ok', 'Anda telah berhasil keluar dari akun.');
    redirect('?page=login');
}

if ($page === 'login') {
    require __DIR__ . '/pages/login.php';
    exit;
}

// Jika pengguna belum login, arahkan ke halaman login
if (!auth_check()) {
    redirect('?page=login');
}

// Jika setelah login tidak ada parameter page, default ke dashboard
if ($page === '') {
    $page = 'dashboard';
}

// Whitelist halaman yang diizinkan
$allowedPages = [
    'dashboard',
    'buku',
    'satuan',
    'vendor',
    'role',
    'user',
    'margin',
    'pengadaan',
    'penerimaan',
    'penjualan',
    'laporan-penjualan',
    'kartu-stok',
    'retur',
];

if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}

$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (is_file($pageFile)) {
    require $pageFile;
} else {
    flash('gagal', 'Halaman tidak ditemukan.');
    redirect('?page=dashboard');
}
