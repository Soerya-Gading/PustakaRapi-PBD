<?php
declare(strict_types=1);
// Tests are write-free on the live DB; transaction tests require disposable schema.
require dirname(__DIR__) . '/includes/db.php';
$base = get_db_connection();
$db = getenv('TOKOKITA_TEST_DB');
if (!$db || !preg_match('/^db_pustakarapi_uji_\d{8}_\d{6}$/', $db)) {
    throw new RuntimeException('Set TOKOKITA_TEST_DB to the disposable test database.');
}
$base->exec('USE `' . $db . '`');
$pdo = $base;
$mode = $argv[1] ?? 'functions';
$checks = 0;
function check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}
function scalar(string $sql, array $params = []): mixed {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    $stmt->closeCursor();
    return $value;
}
function rejects(callable $action, string $label): void {
    try { $action(); } catch (PDOException $e) {
        if ($e->getCode() !== '45000') throw $e;
        check(true, $label);
        return;
    }
    throw new RuntimeException('FAIL: expected SQLSTATE 45000: ' . $label);
}
if ($mode === 'functions') {
    check((int)scalar("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_TYPE='FUNCTION' AND ROUTINE_NAME IN ('fn_hitung_stok','fn_hitung_total_transaksi')") === 2, 'dua function tersedia');
    check((int)scalar('SELECT fn_hitung_total_transaksi(100000,11)') === 111000, 'total + PPN 11 persen');
    check((int)scalar('SELECT fn_hitung_total_transaksi(10005,11)') === 11106, 'PPN dibulatkan per transaksi');
    check((int)scalar('SELECT fn_hitung_total_transaksi(100000,0)') === 100000, 'PPN nol');
    rejects(fn() => scalar('SELECT fn_hitung_total_transaksi(-1,11)'), 'subtotal negatif ditolak');
    rejects(fn() => scalar('SELECT fn_hitung_total_transaksi(100,101)'), 'PPN di luar 0..100 ditolak');
    rejects(fn() => scalar('SELECT fn_hitung_total_transaksi(NULL,11)'), 'subtotal NULL ditolak');
    check(scalar('SELECT fn_hitung_stok(-1)') === null, 'buku tanpa mutasi menghasilkan NULL, bukan stok palsu');
    $book = (int)scalar('SELECT MIN(idbarang) FROM barang');
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO kartu_stok(jenis_transaksi,masuk,keluar,stock,created_at,idtransaksi,idbarang) VALUES('M',10,0,10,NOW(),1,?),('K',0,3,7,NOW(),2,?)")->execute([$book,$book]);
        check((int)scalar('SELECT fn_hitung_stok(?)',[$book]) === 7, 'stok = SUM(masuk) - SUM(keluar)');
    } finally { $pdo->rollBack(); }
}
if ($mode === 'views') {
    check((int)scalar("SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('v_daftar_buku','v_laporan_penjualan','v_ringkasan_stok')") === 3, 'tiga view tersedia');
    check((int)scalar('SELECT COUNT(*) FROM v_daftar_buku') === (int)scalar('SELECT COUNT(*) FROM barang'), 'view katalog satu baris per buku');
    $row = $pdo->query('SELECT * FROM v_ringkasan_stok ORDER BY id_buku LIMIT 1')->fetch();
    check(array_key_exists('nama_satuan',$row) && array_key_exists('tercatat',$row), 'kontrak satuan/tercatat tersedia');
    check((int)$row['tercatat'] === 0 && $row['sisa_stok'] === null && $row['status_stok'] === 'Belum tercatat', 'stok belum tercatat tidak disamakan dengan habis');
    check((int)scalar('SELECT COUNT(*) FROM v_laporan_penjualan') === 0, 'laporan kosong valid tanpa transaksi');
    $columns = $pdo->query('SHOW COLUMNS FROM v_laporan_penjualan')->fetchAll(PDO::FETCH_COLUMN);
    check(!array_diff(['id_penjualan','id_detail','tanggal','id_user','id_buku','kasir','judul_buku','jumlah','harga_satuan','subtotal','ppn','total_nilai'], $columns), 'kolom laporan mendukung filter/detail aplikasi');
}
if ($mode === 'triggers') {
    check((int)scalar("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()") === 2, 'dua trigger tersedia');
    $book=(int)scalar('SELECT MIN(idbarang) FROM barang');
    $user=(int)scalar('SELECT MIN(iduser) FROM `user`');
    $vendor=(int)scalar('SELECT MIN(idvendor) FROM vendor');
    $margin=(int)scalar('SELECT MIN(idmargin_penjualan) FROM margin_penjualan WHERE status=1');
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO pengadaan(timestamp,user_iduser,status,vendor_idvendor,subtotal_nilai,ppn,total_nilai) VALUES(NOW(),?,'P',?,10000,1100,11100)")->execute([$user,$vendor]);
        $po=(int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO penerimaan(created_at,status,idpengadaan,iduser) VALUES(NOW(),'S',?,?)")->execute([$po,$user]);
        $rec=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO detail_penerimaan(idpenerimaan,barang_idbarang,jumlah_terima,harga_satuan_terima,sub_total_terima) VALUES(?,?,10,1000,10000)')->execute([$rec,$book]);
        check((int)scalar('SELECT fn_hitung_stok(?)',[$book])===10, 'trigger penerimaan menambah stok otomatis');
        check((int)scalar("SELECT COUNT(*) FROM kartu_stok WHERE idbarang=? AND jenis_transaksi='M' AND idtransaksi=?",[$book,$rec])===1, 'satu detail penerimaan menghasilkan tepat satu mutasi');
        $pdo->prepare('INSERT INTO penjualan(created_at,subtotal_nilai,ppn,total_nilai,iduser,idmargin_penjualan) VALUES(NOW(),3000,330,3330,?,?)')->execute([$user,$margin]);
        $sale=(int)$pdo->lastInsertId();
        $insert=fn($qty,$sub)=>$pdo->prepare('INSERT INTO detail_penjualan(harga_satuan,jumlah,subtotal,penjualan_idpenjualan,idbarang) VALUES(1000,?,?,?,?)')->execute([$qty,$sub,$sale,$book]);
        $insert(3,3000);
        check((int)scalar('SELECT fn_hitung_stok(?)',[$book])===7, 'trigger penjualan mengurangi stok otomatis');
        check((int)scalar('SELECT stock FROM kartu_stok WHERE idbarang=? ORDER BY idkartu_stok DESC LIMIT 1',[$book])===7, 'saldo berjalan benar');
        rejects(fn()=>$insert(8,8000),'trigger menolak stok tidak cukup');
        rejects(fn()=>$insert(-1,-1000),'trigger menolak jumlah negatif');
        rejects(fn()=>$insert(1,999),'trigger menolak subtotal tidak konsisten');
        check((int)scalar('SELECT fn_hitung_stok(?)',[$book])===7,'penolakan tidak mengubah stok');
        check((int)scalar('SELECT COUNT(*) FROM detail_penjualan WHERE penjualan_idpenjualan=?',[$sale])===1,'detail gagal tidak tertinggal');
        $insert(7,7000);
        check((int)scalar('SELECT fn_hitung_stok(?)',[$book])===0,'penjualan sebesar sisa stok diterima');
    } finally { $pdo->rollBack(); }
    check((int)scalar('SELECT COUNT(*) FROM kartu_stok')===0,'rollback transaksi juga membatalkan efek trigger');
}
if ($mode === 'procedures') {
    check((int)scalar("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() AND ROUTINE_TYPE='PROCEDURE' AND ROUTINE_NAME IN ('sp_simpan_penjualan','sp_terima_pengadaan')")===2,'dua stored procedure tersedia');
    $books=$pdo->query('SELECT idbarang,harga FROM barang WHERE status=1 ORDER BY idbarang LIMIT 2')->fetchAll();
    $b1=(int)$books[0]['idbarang']; $b2=(int)$books[1]['idbarang'];
    $u=(int)scalar('SELECT MIN(iduser) FROM `user`');$v=(int)scalar('SELECT MIN(idvendor) FROM vendor');$m=(int)scalar('SELECT MIN(idmargin_penjualan) FROM margin_penjualan WHERE status=1');
    $call=function(string $name,array $args):int {global $pdo;$s=$pdo->prepare('CALL '.$name.'(?,?,?,@test_out)');$s->execute($args);do{$s->fetchAll();}while($s->nextRowset());$s->closeCursor();return (int)scalar('SELECT @test_out');};
    $pdo->prepare("INSERT INTO pengadaan(timestamp,user_iduser,status,vendor_idvendor,subtotal_nilai,ppn,total_nilai) VALUES(NOW(),?,'P',?,30000,3300,33300)")->execute([$u,$v]);$po=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO detail_pengadaan(harga_satuan,jumlah,sub_total,idbarang,idpengadaan) VALUES(1000,10,10000,?,?),(1000,20,20000,?,?)')->execute([$b1,$po,$b2,$po]);
    rejects(fn()=>$call('sp_terima_pengadaan',[$po,$u,json_encode([['idbarang'=>$b1,'jumlah_terima'=>11,'harga_satuan'=>1000]])]),'penerimaan melebihi pesanan ditolak');
    check((int)scalar('SELECT COUNT(*) FROM penerimaan')===0,'penerimaan gagal rollback header');
    $rec=$call('sp_terima_pengadaan',[$po,$u,json_encode([['idbarang'=>$b1,'jumlah_terima'=>5,'harga_satuan'=>1]])]);
    check($rec>0 && (int)scalar('SELECT fn_hitung_stok(?)',[$b1])===5,'SP penerimaan parsial menambah stok sekali');
    check(scalar('SELECT status FROM pengadaan WHERE idpengadaan=?',[$po])==='P','pengadaan parsial tetap Pending');
    check((int)scalar('SELECT harga_satuan_terima FROM detail_penerimaan WHERE idpenerimaan=?',[$rec])===1000,'harga penerimaan dari pengadaan, bukan manipulasi klien');
    $rec2=$call('sp_terima_pengadaan',[$po,$u,json_encode([['idbarang'=>$b1,'jumlah_terima'=>5],['idbarang'=>$b2,'jumlah_terima'=>20]])]);
    check(scalar('SELECT status FROM pengadaan WHERE idpengadaan=?',[$po])==='S','pengadaan selesai setelah seluruh jumlah diterima');
    check((int)scalar('SELECT COUNT(*) FROM kartu_stok')===3,'tiga detail penerimaan = tiga mutasi, tidak ganda');
    rejects(fn()=>$call('sp_terima_pengadaan',[$po,$u,json_encode([['idbarang'=>$b1,'jumlah_terima'=>1]])]),'pengadaan selesai tidak diterima ulang');
    rejects(fn()=>$call('sp_simpan_penjualan',[$u,$m,'[]']),'keranjang kosong ditolak');
    rejects(fn()=>$call('sp_simpan_penjualan',[$u,$m,json_encode([['idbarang'=>$b1,'jumlah'=>1.5]])]),'jumlah pecahan ditolak');
    rejects(fn()=>$call('sp_simpan_penjualan',[$u,$m,json_encode([['idbarang'=>$b1,'jumlah'=>1],['idbarang'=>$b1,'jumlah'=>1]])]),'item duplikat ditolak');
    rejects(fn()=>$call('sp_simpan_penjualan',[$u,$m,json_encode([['idbarang'=>$b1,'jumlah'=>2],['idbarang'=>$b2,'jumlah'=>999]])]),'stok tidak cukup pada detail kedua ditolak');
    check((int)scalar('SELECT COUNT(*) FROM penjualan')===0 && (int)scalar('SELECT fn_hitung_stok(?)',[$b1])===10,'gagal detail kedua rollback seluruh header/detail/stok');
    $sale=$call('sp_simpan_penjualan',[$u,$m,json_encode([['idbarang'=>$b1,'jumlah'=>2,'harga_satuan'=>1,'subtotal'=>1],['idbarang'=>$b2,'jumlah'=>3]])]);
    $subtotal=2*(int)$books[0]['harga']+3*(int)$books[1]['harga'];
    check((int)scalar('SELECT subtotal_nilai FROM penjualan WHERE idpenjualan=?',[$sale])===$subtotal,'harga penjualan dihitung ulang dari katalog');
    check((int)scalar('SELECT total_nilai FROM penjualan WHERE idpenjualan=?',[$sale])===(int)scalar('SELECT fn_hitung_total_transaksi(?,11)',[$subtotal]),'SP memakai function total transaksi');
    check((int)scalar('SELECT fn_hitung_stok(?)',[$b1])===8 && (int)scalar('SELECT fn_hitung_stok(?)',[$b2])===17,'SP penjualan mengurangi stok sekali');
    check((int)scalar("SELECT COUNT(*) FROM kartu_stok WHERE jenis_transaksi='K'")===2,'dua detail penjualan = dua mutasi keluar');
    check((int)scalar('SELECT COUNT(*) FROM v_laporan_penjualan WHERE id_penjualan=?',[$sale])===2,'laporan memuat multi-detail');
    check((int)scalar('SELECT SUM(total_nilai) FROM (SELECT id_penjualan,MAX(total_nilai) total_nilai FROM v_laporan_penjualan GROUP BY id_penjualan) x')===(int)scalar('SELECT SUM(total_nilai) FROM penjualan'),'agregasi laporan tanpa menggandakan header');
}
if ($mode === 'integration') {
    require dirname(__DIR__).'/includes/helpers.php';
    require dirname(__DIR__).'/includes/adapter.php';
    $book=(int)scalar('SELECT MIN(idbarang) FROM barang');$u=(int)scalar('SELECT MIN(iduser) FROM `user`');$m=(int)scalar('SELECT MIN(idmargin_penjualan) FROM margin_penjualan WHERE status=1');
    $rows=BackendAdapter::getRingkasanStok($pdo)['items'];
    check(isset($rows[0]['tercatat'],$rows[0]['nama_satuan']), 'adapter stok menyertakan satuan/tercatat');
    $unknown=BackendAdapter::hitungStokBuku($pdo,2147483647);
    check($unknown['tercatat']===false,'adapter tidak mengubah NULL stok menjadi Habis');
    $before=(int)scalar('SELECT COUNT(*) FROM kartu_stok');
    $res=BackendAdapter::simpanPenjualan($pdo,$u,$m,[['idbarang'=>$book,'jumlah'=>1,'harga_satuan'=>1,'subtotal'=>1]]);
    check($res['status']==='berhasil' && $res['routine_used']===true,'adapter memakai SP penjualan dan menutup semua result set');
    check((int)scalar('SELECT COUNT(*) FROM kartu_stok')===$before+1,'adapter tidak membuat mutasi ganda');
    $filter=BackendAdapter::getLaporanPenjualan($pdo,'','',$u,$book);
    check($filter['view_available'] && count($filter['items'])>0,'filter kasir/buku pada view bekerja');
    $po=(int)scalar('SELECT MAX(idpengadaan) FROM pengadaan');
    $pdo->prepare("UPDATE pengadaan SET status='P' WHERE idpengadaan=?")->execute([$po]);
    $pdo->prepare('UPDATE detail_pengadaan SET jumlah=jumlah+2,sub_total=(jumlah)*harga_satuan WHERE idpengadaan=? AND idbarang=?')->execute([$po,$book]);
    $before=(int)scalar('SELECT COUNT(*) FROM kartu_stok');
    $res=BackendAdapter::terimaPengadaan($pdo,$po,$u,[['idbarang'=>$book,'jumlah_terima'=>1,'harga_satuan'=>1]]);
    check($res['status']==='berhasil' && $res['routine_used']===true,'adapter memakai SP penerimaan dan menutup result set');
    check((int)scalar('SELECT COUNT(*) FROM kartu_stok')===$before+1,'adapter penerimaan tidak membuat mutasi ganda');
    $before=(int)scalar('SELECT COUNT(*) FROM penjualan');
    $bad=BackendAdapter::simpanPenjualan($pdo,$u,$m,[['idbarang'=>$book,'jumlah'=>999]]);
    check($bad['status']==='stok_tidak_cukup' && (int)scalar('SELECT COUNT(*) FROM penjualan')===$before,'adapter mengembalikan penolakan stok tanpa fallback tulis');
}
if ($mode === 'orphan') {
    $book=(int)scalar('SELECT idbarang FROM kartu_stok WHERE stock>0 ORDER BY idkartu_stok DESC LIMIT 1');
    $before=(int)scalar('SELECT fn_hitung_stok(?)',[$book]);
    rejects(fn()=>$pdo->prepare('INSERT INTO detail_penjualan(harga_satuan,jumlah,subtotal,penjualan_idpenjualan,idbarang) VALUES(1000,1,1000,NULL,?)')->execute([$book]), 'detail penjualan tanpa header ditolak');
    check((int)scalar('SELECT fn_hitung_stok(?)',[$book])===$before,'detail orphan tidak mengurangi stok');
}
if ($mode === 'retur') {
    require dirname(__DIR__).'/includes/helpers.php';
    require dirname(__DIR__).'/includes/retur-service.php';
    $detail=$pdo->query('SELECT dp.iddetail_penerimaan,dp.barang_idbarang,dp.idpenerimaan FROM detail_penerimaan dp ORDER BY dp.iddetail_penerimaan LIMIT 1')->fetch();
    $u=(int)scalar('SELECT MIN(iduser) FROM `user`');$book=(int)$detail['barang_idbarang'];$rec=(int)$detail['idpenerimaan'];$did=(int)$detail['iddetail_penerimaan'];
    $before=(int)scalar('SELECT fn_hitung_stok(?)',[$book]);
    $id=simpan_retur($pdo,$rec,$u,[['iddetail_penerimaan'=>$did,'jumlah'=>1,'alasan'=>'Uji rusak','idbarang'=>2147483647]]);
    check($id>0 && (int)scalar('SELECT fn_hitung_stok(?)',[$book])===$before-1,'retur memakai buku dari detail nyata, bukan input klien');
    check((int)scalar("SELECT COUNT(*) FROM kartu_stok WHERE jenis_transaksi='R' AND idtransaksi=?",[$id])===1,'retur satu mutasi keluar');
    try { simpan_retur($pdo,$rec,$u,[['iddetail_penerimaan'=>$did,'jumlah'=>999,'alasan'=>'Uji']]); check(false,'retur berlebih ditolak'); }
    catch (DomainException $e) {check(true,'retur berlebih ditolak');}
    check((int)scalar('SELECT COUNT(*) FROM retur')===1 && (int)scalar('SELECT fn_hitung_stok(?)',[$book])===$before-1,'retur gagal rollback header dan stok');
}
echo 'CHECKS: ' . $checks . PHP_EOL;
