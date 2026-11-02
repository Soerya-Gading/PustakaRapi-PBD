-- 3. Dua trigger INSERT: hanya trigger ini menulis stok penerimaan/penjualan.
-- Jalur PHP/SP tidak boleh menambahkan mutasi M/K lagi.
-- Transaksi final bersifat append-only; UPDATE/DELETE detail tidak didukung.
DELIMITER $$
CREATE TRIGGER trg_penerimaan_stok_masuk
AFTER INSERT ON detail_penerimaan
FOR EACH ROW
BEGIN
    DECLARE v_barang INT DEFAULT NULL;
    DECLARE v_saldo BIGINT DEFAULT 0;
    IF NEW.jumlah_terima IS NULL OR NEW.jumlah_terima<=0
       OR NEW.harga_satuan_terima IS NULL OR NEW.harga_satuan_terima<0
       OR NEW.sub_total_terima IS NULL
       OR NEW.sub_total_terima <> CAST(NEW.jumlah_terima AS DECIMAL(20,0))*NEW.harga_satuan_terima THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Detail penerimaan tidak valid';
    END IF;
    IF NEW.idpenerimaan IS NULL OR NEW.idpenerimaan>2147483647 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ID penerimaan melampaui kapasitas referensi kartu stok';
    END IF;
    -- Baris barang menjadi mutex per buku, termasuk saat ledger masih kosong.
    SELECT idbarang INTO v_barang FROM barang WHERE idbarang=NEW.barang_idbarang FOR UPDATE;
    IF v_barang IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buku tidak ditemukan'; END IF;
    -- Current locking read: tidak menggunakan snapshot lama transaksi.
    BEGIN
        DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_saldo=0;
        SELECT stock INTO v_saldo FROM kartu_stok WHERE idbarang=NEW.barang_idbarang
        ORDER BY idkartu_stok DESC LIMIT 1 FOR UPDATE;
    END;
    SET v_saldo=COALESCE(v_saldo,0)+NEW.jumlah_terima;
    IF v_saldo>2147483647 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Saldo stok melampaui INT'; END IF;
    INSERT INTO kartu_stok(jenis_transaksi,masuk,keluar,stock,created_at,idtransaksi,idbarang)
    VALUES('M',NEW.jumlah_terima,0,v_saldo,NOW(),NEW.idpenerimaan,NEW.barang_idbarang);
END$$

CREATE TRIGGER trg_penjualan_stok_keluar
AFTER INSERT ON detail_penjualan
FOR EACH ROW
BEGIN
    DECLARE v_barang INT DEFAULT NULL;
    DECLARE v_saldo BIGINT DEFAULT 0;
    IF NEW.penjualan_idpenjualan IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Header penjualan wajib diisi';
    END IF;
    IF NEW.jumlah IS NULL OR NEW.jumlah<=0 OR NEW.harga_satuan IS NULL OR NEW.harga_satuan<0
       OR NEW.subtotal IS NULL OR NEW.subtotal<>CAST(NEW.jumlah AS DECIMAL(20,0))*NEW.harga_satuan THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Detail penjualan tidak valid';
    END IF;
    SELECT idbarang INTO v_barang FROM barang WHERE idbarang=NEW.idbarang FOR UPDATE;
    IF v_barang IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buku tidak ditemukan'; END IF;
    BEGIN
        DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_saldo=0;
        SELECT stock INTO v_saldo FROM kartu_stok WHERE idbarang=NEW.idbarang
        ORDER BY idkartu_stok DESC LIMIT 1 FOR UPDATE;
    END;
    IF COALESCE(v_saldo,0)<NEW.jumlah THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Stok tidak cukup atau belum tercatat';
    END IF;
    INSERT INTO kartu_stok(jenis_transaksi,masuk,keluar,stock,created_at,idtransaksi,idbarang)
    VALUES('K',0,NEW.jumlah,v_saldo-NEW.jumlah,NOW(),NEW.penjualan_idpenjualan,NEW.idbarang);
END$$
DELIMITER ;
