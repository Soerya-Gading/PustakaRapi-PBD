<?php
declare(strict_types=1);

/**
 * PustakaRapi — Global View & Data Helpers
 */

const GENRE = [
    'F' => 'Fiksi',
    'P' => 'Pelajaran',
    'N' => 'Non-fiksi',
    'R' => 'Referensi',
];

const BADAN_HUKUM = [
    'P' => 'PT',
    'C' => 'CV',
    'U' => 'UD',
];

const STATUS_TRANSAKSI = [
    'P' => 'Pending / Proses',
    'S' => 'Selesai',
    'B' => 'Batal',
];

const STATUS_VENDOR = [
    'A' => 'Aktif',
    'N' => 'Nonaktif',
];

function e(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value): string
{
    return 'Rp ' . number_format((float)($value ?? 0), 0, ',', '.');
}

function format_angka($value): string
{
    return number_format((float)($value ?? 0), 0, ',', '.');
}

function format_tanggal(?string $dateStr): string
{
    if (empty($dateStr)) {
        return '—';
    }
    try {
        $dt = new DateTime($dateStr);
        $bulan = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
        ];
        $b = (int)$dt->format('n');
        return $dt->format('d ') . ($bulan[$b] ?? '') . $dt->format(' Y, H:i');
    } catch (Exception $ex) {
        return e($dateStr);
    }
}

function flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
        session_start();
    }
    $_SESSION['flash_notification'] = [
        'type'    => $type, // 'ok', 'error', 'info', 'warning'
        'message' => $message,
    ];
}

function ambil_flash(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
        session_start();
    }
    if (empty($_SESSION['flash_notification'])) {
        return null;
    }
    $f = $_SESSION['flash_notification'];
    unset($_SESSION['flash_notification']);
    return $f;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function render_badge_status(string $status, string $context = 'umum'): string
{
    $status = strtoupper(trim($status));
    $label = $status;
    $class = 'badge-muted';

    if ($context === 'aktif_nonaktif') {
        if ($status === '1' || $status === 'A' || $status === 'AKTIF') {
            $class = 'badge-success';
            $label = 'Aktif';
        } else {
            $class = 'badge-danger';
            $label = 'Nonaktif';
        }
    } elseif ($context === 'transaksi') {
        if ($status === 'S' || $status === 'SELESAI') {
            $class = 'badge-success';
            $label = 'Selesai';
        } elseif ($status === 'P' || $status === 'PROSES') {
            $class = 'badge-warning';
            $label = 'Proses';
        } elseif ($status === 'B' || $status === 'BATAL') {
            $class = 'badge-danger';
            $label = 'Batal';
        }
    } elseif ($context === 'genre') {
        $genreLabels = GENRE;
        $label = $genreLabels[$status] ?? 'Kategori ' . $status;
        $class = 'badge-accent';
    }

    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

function sanitize_input(?string $val): string
{
    return trim((string)($val ?? ''));
}
