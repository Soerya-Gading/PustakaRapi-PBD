<?php
declare(strict_types=1);

/**
 * PustakaRapi — Backend Adapter & Service Layer
 * Menyediakan integrasi transparan untuk View, Stored Procedure, dan Stored Function.
 * Mendeteksi ketersediaan objek database secara otomatis dari MySQL Information Schema.
 */

class BackendAdapter
{
    private static ?array $viewCache = null;
    private static ?array $routineCache = null;

    /**
     * Memeriksa apakah View tertentu telah dibuat di database.
     */
    public static function hasView(PDO $pdo, string $viewName): bool
    {
        if (self::$viewCache === null) {
            $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE()");
            self::$viewCache = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        return in_array($viewName, self::$viewCache, true);
    }

    /**
     * Memeriksa apakah Routine (Procedure / Function) telah dibuat di database.
     */
    public static function hasRoutine(PDO $pdo, string $routineName, string $type = 'PROCEDURE'): bool
    {
        if (self::$routineCache === null) {
            $stmt = $pdo->query("SELECT ROUTINE_NAME, ROUTINE_TYPE FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()");
            self::$routineCache = [];
            foreach ($stmt->fetchAll() as $row) {
                self::$routineCache[$row['ROUTINE_TYPE'] . ':' . strtolower($row['ROUTINE_NAME'])] = true;
            }
        }
        $key = strtoupper($type) . ':' . strtolower($routineName);
        return isset(self::$routineCache[$key]);
    }

    /* =========================================================================
       1. ADAPTER VIEW: v_daftar_buku (Tersedia)
       ========================================================================= */
    public static function getKatalogBuku(PDO $pdo, string $q = '', string $kategori = '', string $urut = 'id'): array
    {
        $hasView = self::hasView($pdo, 'v_daftar_buku');
        
        $orderClauses = [
            'id'    => 'id_buku ASC',
            'judul' => 'judul_buku ASC',
            'murah' => 'harga_jual ASC',
            'mahal' => 'harga_jual DESC',
        ];
        $orderBy = $orderClauses[$urut] ?? 'id_buku ASC';

        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(judul_buku LIKE :q OR id_buku = :idq)';
            $params[':q'] = '%' . $q . '%';
            $params[':idq'] = ctype_digit($q) ? (int)$q : 0;
        }

        if ($kategori !== '' && isset(GENRE[$kategori])) {
            $where[] = 'kode_jenis = :kategori';
            $params[':kategori'] = $kategori;
        }

        if ($hasView) {
            $sql = 'SELECT id_buku, judul_buku, kode_jenis, nama_satuan, harga_jual, kode_status FROM v_daftar_buku';
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY ' . $orderBy;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            return [
                'view_used'   => true,
                'view_name'   => 'v_daftar_buku',
                'status'      => empty($data) ? 'data_kosong' : 'berhasil',
                'items'       => $data,
            ];
        } else {
            // Fallback terisolasi jika view belum ada
            $sql = '
                SELECT b.idbarang AS id_buku, b.nama AS judul_buku, b.jenis AS kode_jenis,
                       s.nama_satuan AS nama_satuan, b.harga AS harga_jual, b.status AS kode_status
                FROM barang b
                LEFT JOIN satuan s ON b.idsatuan = s.idsatuan
            ';
            if ($where) {
                $whereFallback = str_replace(
                    ['id_buku', 'judul_buku', 'kode_jenis'],
                    ['b.idbarang', 'b.nama', 'b.jenis'],
                    implode(' AND ', $where)
                );
                $sql .= ' WHERE ' . $whereFallback;
            }
            $sql .= ' ORDER BY ' . str_replace(['id_buku', 'judul_buku', 'harga_jual'], ['b.idbarang', 'b.nama', 'b.harga'], $orderBy);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            return [
                'view_used'   => false,
                'view_name'   => 'v_daftar_buku',
                'status'      => empty($data) ? 'data_kosong' : 'berhasil',
                'items'       => $data,
            ];
        }
    }

    /* =========================================================================
       2. ADAPTER VIEW: v_laporan_penjualan (Rencana View Tambahan)
       ========================================================================= */
    public static function getLaporanPenjualan(PDO $pdo, string $tglMulai = '', string $tglSelesai = '', ?int $idKasir = null, ?int $idBuku = null): array
    {
        $hasView = self::hasView($pdo, 'v_laporan_penjualan');

        if ($hasView) {
            // Kontrak query langsung ke view v_laporan_penjualan
            $where = [];
            $params = [];

            if ($tglMulai !== '') {
                $where[] = 'DATE(tanggal) >= :tglMulai';
                $params[':tglMulai'] = $tglMulai;
            }
            if ($tglSelesai !== '') {
                $where[] = 'DATE(tanggal) <= :tglSelesai';
                $params[':tglSelesai'] = $tglSelesai;
            }
            if ($idKasir !== null && $idKasir > 0) {
                $where[] = 'id_user = :idKasir';
                $params[':idKasir'] = $idKasir;
            }
            if ($idBuku !== null && $idBuku > 0) {
                $where[] = 'id_buku = :idBuku';
                $params[':idBuku'] = $idBuku;
            }

            $sql = 'SELECT id_penjualan, tanggal, kasir, judul_buku, jumlah, harga_satuan, subtotal, ppn, total_nilai FROM v_laporan_penjualan';
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY id_penjualan DESC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();

            return [
                'view_available' => true,
                'view_name'      => 'v_laporan_penjualan',
                'status'         => empty($data) ? 'data_kosong' : 'berhasil',
                'items'          => $data,
            ];
        }

        // View belum tersedia di database -> Kontrak transparan & fallback query langsung
        $where = [];
        $params = [];

        if ($tglMulai !== '') {
            $where[] = 'DATE(p.created_at) >= :tglMulai';
            $params[':tglMulai'] = $tglMulai;
        }
        if ($tglSelesai !== '') {
            $where[] = 'DATE(p.created_at) <= :tglSelesai';
            $params[':tglSelesai'] = $tglSelesai;
        }
        if ($idKasir !== null && $idKasir > 0) {
            $where[] = 'p.iduser = :idKasir';
            $params[':idKasir'] = $idKasir;
        }
        if ($idBuku !== null && $idBuku > 0) {
            $where[] = 'dp.idbarang = :idBuku';
            $params[':idBuku'] = $idBuku;
        }

        $sql = '
            SELECT 
                p.idpenjualan AS id_penjualan,
                p.created_at AS tanggal,
                u.username AS kasir,
                b.nama AS judul_buku,
                dp.jumlah AS jumlah,
                dp.harga_satuan AS harga_satuan,
                dp.subtotal AS subtotal,
                p.ppn AS ppn,
                p.total_nilai AS total_nilai
            FROM penjualan p
            LEFT JOIN `user` u ON p.iduser = u.iduser
            LEFT JOIN detail_penjualan dp ON p.idpenjualan = dp.penjualan_idpenjualan
            LEFT JOIN barang b ON dp.idbarang = b.idbarang
        ';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.idpenjualan DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        return [
            'view_available' => false,
            'view_name'      => 'v_laporan_penjualan',
            'status'         => empty($data) ? 'data_kosong' : 'berhasil',
            'items'          => $data,
        ];
    }

    /* =========================================================================
       3. ADAPTER VIEW: v_ringkasan_stok (Rencana View Tambahan)
       ========================================================================= */
    public static function getRingkasanStok(PDO $pdo, ?int $idBuku = null): array
    {
        $hasView = self::hasView($pdo, 'v_ringkasan_stok');

        if ($hasView) {
            $sql = 'SELECT id_buku, judul_buku, nama_satuan, total_masuk, total_keluar, sisa_stok, status_stok, tercatat, riwayat_count FROM v_ringkasan_stok';
            $params = [];
            if ($idBuku !== null && $idBuku > 0) {
                $sql .= ' WHERE id_buku = :idBuku';
                $params[':idBuku'] = $idBuku;
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();

            return [
                'view_available' => true,
                'view_name'      => 'v_ringkasan_stok',
                'status'         => empty($data) ? 'data_kosong' : 'berhasil',
                'items'          => $data,
            ];
        }

        // View belum tersedia di database -> Kontrak transparan & hitung dari kartu_stok / barang
        $sql = '
            SELECT 
                b.idbarang AS id_buku,
                b.nama AS judul_buku,
                s.nama_satuan,
                COALESCE(SUM(ks.masuk), 0) AS total_masuk,
                COALESCE(SUM(ks.keluar), 0) AS total_keluar,
                COUNT(ks.idkartu_stok) AS riwayat_count,
                (COALESCE(SUM(ks.masuk), 0) - COALESCE(SUM(ks.keluar), 0)) AS sisa_stok
            FROM barang b
            LEFT JOIN satuan s ON b.idsatuan = s.idsatuan
            LEFT JOIN kartu_stok ks ON b.idbarang = ks.idbarang
        ';
        $params = [];
        if ($idBuku !== null && $idBuku > 0) {
            $sql .= ' WHERE b.idbarang = :idBuku';
            $params[':idBuku'] = $idBuku;
        }
        $sql .= ' GROUP BY b.idbarang, b.nama, s.nama_satuan ORDER BY b.idbarang ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $items = [];
        foreach ($rows as $r) {
            $recorded = (int)$r['riwayat_count'] > 0;
            $sisa = (int)$r['sisa_stok'];
            if (!$recorded) {
                $statusStok = 'Belum tercatat';
            } elseif ($sisa <= 0) {
                $statusStok = 'Habis';
            } elseif ($sisa <= 5) {
                $statusStok = 'Menipis';
            } else {
                $statusStok = 'Aman';
            }

            $items[] = [
                'id_buku'       => (int)$r['id_buku'],
                'judul_buku'    => (string)$r['judul_buku'],
                'nama_satuan'   => (string)$r['nama_satuan'],
                'total_masuk'   => (int)$r['total_masuk'],
                'total_keluar'  => (int)$r['total_keluar'],
                'sisa_stok'     => $sisa,
                'status_stok'   => $statusStok,
                'tercatat'      => $recorded,
            ];
        }

        return [
            'view_available' => false,
            'view_name'      => 'v_ringkasan_stok',
            'status'         => empty($items) ? 'data_kosong' : 'berhasil',
            'items'          => $items,
        ];
    }

    /* =========================================================================
       4. FUNCTION: Perhitungan Total Transaksi (fn_hitung_total_transaksi)
       ========================================================================= */
    public static function hitungTotal(PDO $pdo, int $subtotal, float $ppnPersen = 11.0): array
    {
        $hasFn = self::hasRoutine($pdo, 'fn_hitung_total_transaksi', 'FUNCTION');
        if ($hasFn) {
            try {
                $stmt = $pdo->prepare('SELECT fn_hitung_total_transaksi(?, ?) AS total');
                $stmt->execute([$subtotal, $ppnPersen]);
                $total = (int)$stmt->fetchColumn();
                $ppn = $total - $subtotal;
                return [
                    'routine_used' => true,
                    'routine_name' => 'fn_hitung_total_transaksi',
                    'subtotal'     => $subtotal,
                    'ppn'          => $ppn,
                    'total'        => $total,
                ];
            } catch (Exception $e) {
                // fallback jika eksekusi function gagal
            }
        }

        $ppn = (int)round(($subtotal * $ppnPersen) / 100);
        $total = $subtotal + $ppn;
        return [
            'routine_used' => false,
            'routine_name' => 'fn_hitung_total_transaksi',
            'routine_status' => 'routine_belum_tersedia',
            'subtotal'     => $subtotal,
            'ppn'          => $ppn,
            'total'        => $total,
        ];
    }

    /* =========================================================================
       5. FUNCTION: Perhitungan Stok Buku (fn_hitung_stok)
       ========================================================================= */
    public static function hitungStokBuku(PDO $pdo, int $idbarang): array
    {
        $hasFn = self::hasRoutine($pdo, 'fn_hitung_stok', 'FUNCTION');
        if ($hasFn) {
            try {
                $stmt = $pdo->prepare('SELECT fn_hitung_stok(?) AS sisa_stok');
                $stmt->execute([$idbarang]);
                $value = $stmt->fetchColumn();
                $stmt->closeCursor();
                $tercatat = $value !== null && $value !== false;
                $sisa = (int)$value;
                return [
                    'routine_used' => true,
                    'routine_name' => 'fn_hitung_stok',
                    'tercatat'     => $tercatat,
                    'stok'         => $sisa,
                    'status'       => !$tercatat ? 'Belum tercatat' : ($sisa <= 0 ? 'Habis' : ($sisa <= 5 ? 'Menipis' : 'Aman')),
                ];
            } catch (Exception $e) {
                // fallback
            }
        }

        // Cek kartu stok
        $stmt = $pdo->prepare('
            SELECT 
                COUNT(*) AS riwayat_count,
                COALESCE(SUM(masuk), 0) - COALESCE(SUM(keluar), 0) AS sisa_stok
            FROM kartu_stok
            WHERE idbarang = ?
        ');
        $stmt->execute([$idbarang]);
        $res = $stmt->fetch();

        $tercatat = ((int)$res['riwayat_count']) > 0;
        $sisa = (int)$res['sisa_stok'];

        if (!$tercatat) {
            $status = 'Belum tercatat';
        } elseif ($sisa <= 0) {
            $status = 'Habis';
        } elseif ($sisa <= 5) {
            $status = 'Menipis';
        } else {
            $status = 'Aman';
        }

        return [
            'routine_used'   => false,
            'routine_name'   => 'fn_hitung_stok',
            'routine_status' => 'routine_belum_tersedia',
            'tercatat'       => $tercatat,
            'stok'           => $sisa,
            'status'         => $status,
        ];
    }

    /** CALL owns its transaction. No PHP ledger inserts for M/K. */
    private static function callTransaction(PDO $pdo, string $routine, array $params): int
    {
        if ($pdo->inTransaction()) {
            throw new RuntimeException('Panggilan SP tidak boleh di dalam transaksi PHP.');
        }
        if (!self::hasRoutine($pdo, $routine)) {
            throw new RuntimeException('Stored procedure belum terpasang. Jalankan migrasi database.');
        }
        $triggerCount = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN ('trg_penerimaan_stok_masuk','trg_penjualan_stok_keluar')")->fetchColumn();
        if ($triggerCount !== 2) {
            throw new RuntimeException('Trigger stok belum lengkap. Transaksi dibatalkan.');
        }
        $pdo->exec('SET @tokokita_out_id = NULL');
        $stmt = $pdo->prepare('CALL ' . $routine . '(?, ?, ?, @tokokita_out_id)');
        try {
            $stmt->execute($params);
            do { $stmt->fetchAll(); } while ($stmt->nextRowset());
        } finally {
            $stmt->closeCursor();
        }
        $id = (int)$pdo->query('SELECT @tokokita_out_id')->fetchColumn();
        if ($id <= 0) throw new RuntimeException('Stored procedure tidak mengembalikan ID transaksi.');
        return $id;
    }

    private static function transactionError(Throwable $ex): array
    {
        error_log('[TokoKita Transaksi] ' . $ex->getMessage());
        $pesan = $ex instanceof PDOException && $ex->getCode() === '45000'
            ? (string)($ex->errorInfo[2] ?? 'Data transaksi ditolak database.')
            : ($ex instanceof RuntimeException && !$ex instanceof PDOException
                ? $ex->getMessage() : 'Transaksi gagal. Muat ulang data dan coba kembali.');
        return [
            'status' => str_contains(strtolower($pesan), 'stok tidak cukup') ? 'stok_tidak_cukup' : 'gagal',
            'routine_used' => true,
            'pesan' => $pesan,
        ];
    }

    public static function simpanPenjualan(PDO $pdo, int $iduser, int $idmargin, array $items): array
    {
        try {
            $id = self::callTransaction($pdo, 'sp_simpan_penjualan', [
                $iduser, $idmargin, json_encode(array_values($items), JSON_THROW_ON_ERROR),
            ]);
            return ['status'=>'berhasil', 'routine_used'=>true, 'routine_name'=>'sp_simpan_penjualan',
                'idpenjualan'=>$id, 'pesan'=>'Penjualan #' . $id . ' tersimpan melalui stored procedure.'];
        } catch (Throwable $ex) { return self::transactionError($ex); }
    }

    public static function terimaPengadaan(PDO $pdo, int $idpengadaan, int $iduser, array $itemsTerima): array
    {
        try {
            $id = self::callTransaction($pdo, 'sp_terima_pengadaan', [
                $idpengadaan, $iduser, json_encode(array_values($itemsTerima), JSON_THROW_ON_ERROR),
            ]);
            return ['status'=>'berhasil', 'routine_used'=>true, 'routine_name'=>'sp_terima_pengadaan',
                'idpenerimaan'=>$id, 'pesan'=>'Penerimaan #' . $id . ' tersimpan; stok diperbarui trigger.'];
        } catch (Throwable $ex) { return self::transactionError($ex); }
    }
}

