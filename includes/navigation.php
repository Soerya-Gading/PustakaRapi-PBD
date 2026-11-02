<?php
declare(strict_types=1);

/**
 * PustakaRapi — Modern Sidebar Navigation
 */

$currentPage = $_GET['page'] ?? 'dashboard';

$menuGroups = [
    'Ringkasan' => [
        'dashboard' => [
            'label' => 'Dashboard',
            'icon'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>',
        ],
    ],
    'Transaksi & Operasional' => [
        'penjualan' => [
            'label' => 'Penjualan / Kasir',
            'icon'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>',
        ],
        'pengadaan' => [
            'label' => 'Pengadaan',
            'icon'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>',
        ],
        'penerimaan' => [
            'label' => 'Penerimaan',
            'icon'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        ],
        'retur' => [
            'label' => 'Retur Buku',
            'icon'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>',
        ],
    ],
    'Katalog & Persediaan' => [
        'buku' => [
            'label' => 'Data Buku',
            'icon'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
        ],
        'kartu-stok' => [
            'label' => 'Kartu Stok',
            'icon'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>',
        ],
        'laporan-penjualan' => [
            'label' => 'Laporan Penjualan',
            'icon'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        ],
    ],
    'Data Master' => [
        'satuan' => [
            'label' => 'Satuan',
            'icon'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
        ],
        'vendor' => [
            'label' => 'Vendor / Penerbit',
            'icon'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
        ],
        'margin' => [
            'label' => 'Margin Penjualan',
            'icon'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="19" y1="5" x2="5" y2="19"/><circle cx="9" cy="9" r="2"/><circle cx="15" cy="15" r="2"/></svg>',
        ],
        'role' => [
            'label' => 'Role Akses',
            'icon'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        ],
        'user' => [
            'label' => 'Pengguna',
            'icon'  => '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        ],
    ],
];
?>
<aside class="app-sidebar" id="app-sidebar">
    <div class="sidebar-header">
        <a href="?page=dashboard" class="brand-logo" aria-label="TokoKita Dashboard">
            <img src="public/images/tokokita-symbol-purple.png" alt="" class="brand-logo-image">
            <span class="brand-logo-copy">
                <strong>PustakaRapi</strong>
                <small>SINCE 2026</small>
            </span>
        </a>
        <span class="brand-badge">TOKO</span>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menuGroups as $groupTitle => $navItems): ?>
            <?php 
            $visibleItems = array_filter($navItems, function($meta, $key) {
                return auth_has_permission($key);
            }, ARRAY_FILTER_USE_BOTH);
            if (empty($visibleItems)) continue;
            ?>
            <div class="nav-section">
                <p class="nav-section-title"><?= e($groupTitle) ?></p>
                <ul class="nav-list">
                    <?php foreach ($visibleItems as $key => $meta): ?>
                        <li>
                            <a href="?page=<?= e($key) ?>" class="nav-link <?= $currentPage === $key ? 'active' : '' ?>">
                                <?= $meta['icon'] ?>
                                <span><?= e($meta['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-profile-badge">
            <div class="user-avatar">
                <?= strtoupper(substr(auth_username(), 0, 1)) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= e(auth_username()) ?></div>
                <span class="user-role-pill"><?= e(auth_role_name()) ?></span>
            </div>
            <form action="?page=logout" method="post" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn-logout" title="Keluar / Logout">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</aside>

