<?php
declare(strict_types=1);

/**
 * PustakaRapi — Modern Layout Header
 */

$currentPage = $_GET['page'] ?? 'dashboard';

$pageTitles = [
    'dashboard'         => 'Dashboard & Ringkasan',
    'buku'              => 'Data Buku & Katalog',
    'satuan'            => 'Data Satuan',
    'vendor'            => 'Data Vendor & Penerbit',
    'role'              => 'Manajemen Role Akses',
    'user'              => 'Manajemen Pengguna',
    'margin'            => 'Margin Penjualan',
    'pengadaan'         => 'Pengadaan Buku',
    'penerimaan'        => 'Penerimaan Buku',
    'penjualan'         => 'Kasir & Penjualan',
    'laporan-penjualan' => 'Laporan Penjualan',
    'kartu-stok'        => 'Kartu Stok & Mutasi',
    'retur'             => 'Retur Penerimaan',
];

$pageTitle = $pageTitles[$currentPage] ?? 'Sistem Operasional';
$flash = ambil_flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · TokoKita</title>
    <link rel="stylesheet" href="public/css/app.css">
    <meta name="theme-color" content="#2D123F">
</head>
<body>

<div class="app-layout">
    <?php include __DIR__ . '/navigation.php'; ?>

    <main class="app-main">
        <header class="app-topbar">
            <div style="display:flex; align-items:center; gap:14px;">
                <button type="button" class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Buka Menu">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <div class="topbar-breadcrumbs">
                    <span>TokoKita</span>
                    <span class="sep">/</span>
                    <span class="current"><?= e($pageTitle) ?></span>
                </div>
            </div>

            <div class="topbar-actions">
                <span class="badge badge-accent" style="display:inline-flex; align-items:center; gap:6px;">
                    <span style="width:7px; height:7px; border-radius:50%; background:currentColor;"></span>
                    <?= e(auth_role_name()) ?>
                </span>
            </div>
        </header>

        <div class="content-container">
            <?php if ($flash): ?>
                <?php
                $alertClass = 'alert-info';
                if ($flash['type'] === 'ok' || $flash['type'] === 'success') {
                    $alertClass = 'alert-ok';
                } elseif ($flash['type'] === 'gagal' || $flash['type'] === 'error' || $flash['type'] === 'danger') {
                    $alertClass = 'alert-bad';
                } elseif ($flash['type'] === 'warning') {
                    $alertClass = 'alert-warning';
                }
                ?>
                <div class="alert-banner <?= $alertClass ?>">
                    <div style="flex-shrink:0;">
                        <?php if ($alertClass === 'alert-ok'): ?>
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php elseif ($alertClass === 'alert-bad'): ?>
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;"><?= e($flash['message']) ?></div>
                </div>
            <?php endif; ?>

