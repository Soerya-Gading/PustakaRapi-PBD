<?php
declare(strict_types=1);

/**
 * PustakaRapi — Database Connector
 * Membangun koneksi PDO MySQL dengan error handling yang aman.
 */

if (!defined('PUSTAKARAPI_INIT')) {
    define('PUSTAKARAPI_INIT', true);
}

function get_db_connection(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $configFile = dirname(__DIR__) . '/config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('File konfigurasi config.php tidak ditemukan.');
    }

    $config = require $configFile;

    $localConfigFile = dirname(__DIR__) . '/config.local.php';
    if (is_file($localConfigFile)) {
        $localConfig = require $localConfigFile;
        if (is_array($localConfig)) {
            $config = array_replace($config, $localConfig);
        }
    }

    $host = (string)($config['host'] ?? '127.0.0.1');
    $port = (int)($config['port'] ?? 3306);
    $database = (string)($config['database'] ?? 'db_pustakarapi');
    $username = (string)($config['username'] ?? 'root');
    $password = (string)($config['password'] ?? '');

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        return $pdo;
    } catch (PDOException $ex) {
        // Jangan ekspos password atau detail kredensial ke layar
        error_log('[PustakaRapi DB Error] ' . $ex->getMessage());
        throw new RuntimeException('Koneksi ke database gagal. Pastikan MySQL pada Laragon sudah berjalan.');
    }
}

