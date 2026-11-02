<?php
declare(strict_types=1);

/** Retur tetap PHP transaction: tidak memakai trigger M/K, mutex stok sama. */
function simpan_retur(PDO $pdo, int $idpenerimaan, int $iduser, array $items): int
{
    if (!$items || count($items)>100 || $idpenerimaan<=0 || $iduser<=0) {
        throw new DomainException('Data retur tidak valid.');
    }
    $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT idpenerimaan FROM penerimaan WHERE idpenerimaan=? FOR UPDATE');
        $stmt->execute([$idpenerimaan]);
        if (!$stmt->fetchColumn()) throw new DomainException('Penerimaan tidak ditemukan.');
        $details=[];
        foreach ($items as $item) {
            $id=(int)($item['iddetail_penerimaan']??0);
            $qty=filter_var($item['jumlah']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if (!$id || $qty===false || isset($details[$id])) throw new DomainException('Detail/jumlah retur tidak valid atau duplikat.');
            $stmt=$pdo->prepare('SELECT barang_idbarang,jumlah_terima FROM detail_penerimaan WHERE iddetail_penerimaan=? AND idpenerimaan=? FOR UPDATE');
            $stmt->execute([$id,$idpenerimaan]);$detail=$stmt->fetch();
            if (!$detail) throw new DomainException('Detail bukan milik penerimaan terpilih.');
            $details[$id]=['id'=>$id,'book'=>(int)$detail['barang_idbarang'],'received'=>(int)$detail['jumlah_terima'],
                'qty'=>$qty,'reason'=>mb_substr(trim((string)($item['alasan']??'Rusak')),0,200)];
        }
        uasort($details,fn($a,$b)=>[$a['book'],$a['id']]<=>[$b['book'],$b['id']]);
        $pdo->prepare('INSERT INTO retur(created_at,idpenerimaan,iduser) VALUES(NOW(),?,?)')->execute([$idpenerimaan,$iduser]);
        $idretur=(int)$pdo->lastInsertId();
        if ($idretur>2147483647) throw new DomainException('ID retur melampaui kapasitas kartu stok.');
        foreach($details as $detail) {
            $pdo->prepare('SELECT idbarang FROM barang WHERE idbarang=? FOR UPDATE')->execute([$detail['book']]);
            $stmt=$pdo->prepare('SELECT COALESCE(SUM(jumlah),0) FROM detail_retur WHERE iddetail_penerimaan=?');
            $stmt->execute([$detail['id']]);$returned=(int)$stmt->fetchColumn();
            if ($detail['qty']>$detail['received']-$returned) throw new DomainException('Jumlah retur melebihi sisa barang yang diterima.');
            $stmt=$pdo->prepare('SELECT stock FROM kartu_stok WHERE idbarang=? ORDER BY idkartu_stok DESC LIMIT 1 FOR UPDATE');
            $stmt->execute([$detail['book']]);$stock=(int)$stmt->fetchColumn();
            if ($detail['qty']>$stock) throw new DomainException('Stok tidak cukup untuk retur.');
            $pdo->prepare('INSERT INTO detail_retur(jumlah,alasan,idretur,iddetail_penerimaan) VALUES(?,?,?,?)')
                ->execute([$detail['qty'],$detail['reason'],$idretur,$detail['id']]);
            $pdo->prepare("INSERT INTO kartu_stok(jenis_transaksi,masuk,keluar,stock,created_at,idtransaksi,idbarang) VALUES('R',0,?,?,NOW(),?,?)")
                ->execute([$detail['qty'],$stock-$detail['qty'],$idretur,$detail['book']]);
        }
        $pdo->commit();
        return $idretur;
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
