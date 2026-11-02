<?php
declare(strict_types=1);

/**
 * Action: Proses Keluar (Logout)
 */

auth_logout();
flash('ok', 'Anda telah berhasil keluar dari sesi sistem.');
redirect('?page=login');

