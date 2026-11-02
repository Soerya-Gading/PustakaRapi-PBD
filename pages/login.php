<?php
declare(strict_types=1);

/**
 * PustakaRapi — Halaman Masuk (Login)
 */

if (auth_check()) {
    redirect('?page=dashboard');
}

$flash = ambil_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Sistem · PustakaRapi</title>
    <link rel="stylesheet" href="public/css/app.css">
    <meta name="theme-color" content="#2D123F">
</head>
<body>

<div class="login-screen">
    <div class="login-card">
        <div class="login-brand">
            <img src="public/images/tokokita-symbol-purple.png" alt="" class="login-logo-image">
            <span class="login-brand-copy">
                <strong>PustakaRapi</strong>
                <small>SINCE 2026</small>
            </span>
        </div>
        <p class="login-subtitle">Masuk untuk mengelola operasional katalog, persediaan, dan kasir.</p>

        <?php if ($flash): ?>
            <?php
            $alertClass = 'alert-info';
            if ($flash['type'] === 'ok' || $flash['type'] === 'success') {
                $alertClass = 'alert-ok';
            } elseif ($flash['type'] === 'gagal' || $flash['type'] === 'error') {
                $alertClass = 'alert-bad';
            } elseif ($flash['type'] === 'warning') {
                $alertClass = 'alert-warning';
            }
            ?>
            <div class="alert-banner <?= $alertClass ?>" style="margin-bottom:20px;">
                <div style="flex:1;"><?= e($flash['message']) ?></div>
            </div>
        <?php endif; ?>

        <form method="post" action="?action=auth_login" id="form-login">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="login-username">Nama Pengguna (Username)</label>
                <input type="text" name="username" id="login-username" class="form-control" required autofocus placeholder="Masukkan username Anda">
            </div>

            <div class="form-group">
                <label class="form-label" for="login-password">Kata Sandi (Password)</label>
                <div class="password-field-wrap">
                    <input type="password" name="password" id="login-password" class="form-control" required placeholder="Masukkan kata sandi">
                    <button type="button" class="btn-toggle-password" aria-label="Tampilkan Kata Sandi">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="7" r="3"></circle>
                        </svg>
                    </button>
                </div>
            </div>

            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; font-size:0.86rem;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:var(--text-muted);">
                    <input type="checkbox" name="remember_me" value="1" style="accent-color:var(--accent-primary);">
                    <span>Ingat sesi masuk di perangkat ini</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" id="btn-submit-login" style="width:100%; min-height:46px;">
                <span id="login-btn-text">Masuk ke Sistem</span>
                <span id="login-btn-loading" style="display:none;">Memverifikasi kredensial...</span>
            </button>
        </form>

        <div style="margin-top:28px; padding-top:20px; border-top:1px dashed var(--border-light); font-size:0.78rem; color:var(--text-muted);">
            <div style="font-weight:700; text-transform:uppercase; letter-spacing:0.04em; margin-bottom:6px; color:var(--text-main);">
                Akun Standar Sistem (Role Evaluasi):
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:6px;">
                <div>• Administrator: <code>admin</code> / <code>admin123</code></div>
                <div>• Kasir: <code>kasir</code> / <code>kasir123</code></div>
                <div>• Gudang: <code>gudang</code> / <code>gudang123</code></div>
                <div>• Purchasing: <code>purchasing</code> / <code>purchasing123</code></div>
                <div style="grid-column: 1 / -1;">• Supervisor: <code>supervisor</code> / <code>supervisor123</code></div>
            </div>
        </div>
    </div>
</div>

<script src="public/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('form-login');
    const submitBtn = document.getElementById('btn-submit-login');
    const textSpan  = document.getElementById('login-btn-text');
    const loadSpan  = document.getElementById('login-btn-loading');

    if (loginForm && submitBtn) {
        loginForm.addEventListener('submit', () => {
            textSpan.style.display = 'none';
            loadSpan.style.display = 'inline';
            submitBtn.style.opacity = '0.75';
            submitBtn.style.pointerEvents = 'none';
        });
    }
});
</script>
</body>
</html>

