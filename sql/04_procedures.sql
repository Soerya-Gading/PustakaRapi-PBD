-- 4. Dua SP. Dipanggil dengan CALL, JSON multi-item dan parameter OUT.
-- SP memiliki transaksi sendiri. Jangan bungkus CALL dengan beginTransaction PHP.
-- PPN 11% mengikuti simulasi aplikasi, bukan klaim tarif pajak resmi.
-- barang.harga sudah harga jual; margin hanya referensi, tidak ditambahkan dua kali.
DELIMITER $$
CREATE PROCEDURE sp_simpan_penjualan(
    IN p_iduser INT, IN p_idmargin INT, IN p_items JSON, OUT p_idpenjualan INT
)
SQL SECURITY INVOKER
BEGIN
    DECLARE v_i INT DEFAULT 0;
    DECLARE v_n INT;
    DECLARE v_item JSON;
    DECLARE v_id INT;
    DECLARE v_qty INT;
    DECLARE v_lock INT;
    DECLARE v_harga BIGINT;
    DECLARE v_sub BIGINT DEFAULT 0;
    DECLARE v_total BIGINT;
    DECLARE v_active INT;
    SET p_idpenjualan=NULL;
    IF p_items IS NULL OR JSON_TYPE(p_items)<>'ARRAY' OR JSON_LENGTH(p_items)=0 OR JSON_LENGTH(p_items)>100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Keranjang harus berisi 1 sampai 100 item';
    END IF;
    SET v_n=JSON_LENGTH(p_items);
    WHILE v_i<v_n DO
        SET v_item=JSON_EXTRACT(p_items,CONCAT('$[',v_i,']'));
        IF JSON_TYPE(v_item)<>'OBJECT'
           OR COALESCE(JSON_TYPE(JSON_EXTRACT(v_item,'$.idbarang')),'NULL')<>'INTEGER'
           OR COALESCE(JSON_TYPE(JSON_EXTRACT(v_item,'$.jumlah')),'NULL')<>'INTEGER'
           OR CAST(JSON_UNQUOTE(JSON_EXTRACT(v_item,'$.idbarang')) AS DECIMAL(30,0)) NOT BETWEEN 1 AND 2147483647
           OR CAST(JSON_UNQUOTE(JSON_EXTRACT(v_item,'$.jumlah')) AS DECIMAL(30,0)) NOT BETWEEN 1 AND 2147483647 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ID/jumlah wajib bilangan bulat positif';
        END IF;
        SET v_i=v_i+1;
    END WHILE;
    IF (SELECT COUNT(*)-COUNT(DISTINCT j.idbarang) FROM JSON_TABLE(p_items,'$[*]' COLUMNS(idbarang INT PATH '$.idbarang')) j)>0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buku duplikat dalam keranjang';
    END IF;
    BEGIN
        DECLARE EXIT HANDLER FOR SQLEXCEPTION
        BEGIN ROLLBACK; SET p_idpenjualan=NULL; RESIGNAL; END;
        START TRANSACTION;
        IF NOT EXISTS(SELECT 1 FROM `user` WHERE iduser=p_iduser) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='User tidak ditemukan';
        END IF;
        SET v_active=NULL;
        SELECT status INTO v_active FROM margin_penjualan WHERE idmargin_penjualan=p_idmargin FOR SHARE;
        IF v_active IS NULL OR v_active<>1 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Margin tidak aktif atau tidak ditemukan';
        END IF;
        INSERT INTO penjualan(created_at,subtotal_nilai,ppn,total_nilai,iduser,idmargin_penjualan)
        VALUES(NOW(),0,0,0,p_iduser,p_idmargin);
        SET p_idpenjualan=LAST_INSERT_ID();
        SET v_i=0;
        WHILE v_i<v_n DO
            -- Urutkan lock per ID untuk mengurangi risiko deadlock multi-buku.
            SELECT j.idbarang,j.jumlah INTO v_id,v_qty
            FROM JSON_TABLE(p_items,'$[*]' COLUMNS(idbarang INT PATH '$.idbarang',jumlah INT PATH '$.jumlah')) j
            ORDER BY j.idbarang LIMIT v_i,1;
            SET v_lock=NULL; SET v_harga=NULL; SET v_active=NULL;
            SELECT idbarang,harga,status INTO v_lock,v_harga,v_active FROM barang WHERE idbarang=v_id FOR UPDATE;
            IF v_lock IS NULL OR v_harga IS NULL OR v_harga<0 OR v_active<>1 OR v_active IS NULL THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buku tidak aktif atau harga tidak valid';
            END IF;
            IF v_harga*v_qty>2147483647 OR v_sub+v_harga*v_qty>2147483647 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Nilai transaksi melampaui kapasitas INT';
            END IF;
            -- Trigger detail memvalidasi stok dan menulis mutasi K, bukan SP ini.
            INSERT INTO detail_penjualan(harga_satuan,jumlah,subtotal,penjualan_idpenjualan,idbarang)
            VALUES(v_harga,v_qty,v_harga*v_qty,p_idpenjualan,v_id);
            SET v_sub=v_sub+v_harga*v_qty;
            SET v_i=v_i+1;
        END WHILE;
        SET v_total=fn_hitung_total_transaksi(v_sub,11);
        IF v_total>2147483647 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Total transaksi melampaui kapasitas INT'; END IF;
        UPDATE penjualan SET subtotal_nilai=v_sub,ppn=v_total-v_sub,total_nilai=v_total WHERE idpenjualan=p_idpenjualan;
        COMMIT;
    END;
END$$

CREATE PROCEDURE sp_terima_pengadaan(
    IN p_idpengadaan BIGINT, IN p_iduser INT, IN p_items JSON, OUT p_idpenerimaan BIGINT
)
SQL SECURITY INVOKER
BEGIN
    DECLARE v_i INT DEFAULT 0;
    DECLARE v_n INT;
    DECLARE v_item JSON;
    DECLARE v_id INT;
    DECLARE v_qty INT;
    DECLARE v_status CHAR(1);
    DECLARE v_lock INT;
    DECLARE v_count INT;
    DECLARE v_pesan BIGINT;
    DECLARE v_diterima BIGINT;
    DECLARE v_harga BIGINT;
    SET p_idpenerimaan=NULL;
    IF p_items IS NULL OR JSON_TYPE(p_items)<>'ARRAY' OR JSON_LENGTH(p_items)=0 OR JSON_LENGTH(p_items)>100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Penerimaan harus berisi 1 sampai 100 item';
    END IF;
    SET v_n=JSON_LENGTH(p_items);
    WHILE v_i<v_n DO
        SET v_item=JSON_EXTRACT(p_items,CONCAT('$[',v_i,']'));
        IF JSON_TYPE(v_item)<>'OBJECT'
           OR COALESCE(JSON_TYPE(JSON_EXTRACT(v_item,'$.idbarang')),'NULL')<>'INTEGER'
           OR COALESCE(JSON_TYPE(JSON_EXTRACT(v_item,'$.jumlah_terima')),'NULL')<>'INTEGER'
           OR CAST(JSON_UNQUOTE(JSON_EXTRACT(v_item,'$.idbarang')) AS DECIMAL(30,0)) NOT BETWEEN 1 AND 2147483647
           OR CAST(JSON_UNQUOTE(JSON_EXTRACT(v_item,'$.jumlah_terima')) AS DECIMAL(30,0)) NOT BETWEEN 1 AND 2147483647 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ID/jumlah terima wajib bilangan bulat positif';
        END IF;
        SET v_i=v_i+1;
    END WHILE;
    IF (SELECT COUNT(*)-COUNT(DISTINCT j.idbarang) FROM JSON_TABLE(p_items,'$[*]' COLUMNS(idbarang INT PATH '$.idbarang')) j)>0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buku duplikat dalam penerimaan';
    END IF;
    BEGIN
        DECLARE EXIT HANDLER FOR SQLEXCEPTION
        BEGIN ROLLBACK; SET p_idpenerimaan=NULL; RESIGNAL; END;
        START TRANSACTION;
        -- Kunci PO sebelum consistent read pertama agar snapshot penerimaan
        -- tidak tertinggal saat CALL lain sudah commit sambil kita menunggu lock.
        SET v_status=NULL;
        SELECT status INTO v_status FROM pengadaan WHERE idpengadaan=p_idpengadaan FOR UPDATE;
        IF v_status IS NULL OR v_status<>'P' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pengadaan tidak Pending atau tidak ditemukan';
        END IF;
        IF NOT EXISTS(SELECT 1 FROM `user` WHERE iduser=p_iduser) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='User tidak ditemukan'; END IF;
        INSERT INTO penerimaan(created_at,status,idpengadaan,iduser) VALUES(NOW(),'S',p_idpengadaan,p_iduser);
        SET p_idpenerimaan=LAST_INSERT_ID();
        SET v_i=0;
        WHILE v_i<v_n DO
            SELECT j.idbarang,j.jumlah_terima INTO v_id,v_qty
            FROM JSON_TABLE(p_items,'$[*]' COLUMNS(idbarang INT PATH '$.idbarang',jumlah_terima INT PATH '$.jumlah_terima')) j
            ORDER BY j.idbarang LIMIT v_i,1;
            SET v_lock=NULL;
            SELECT idbarang INTO v_lock FROM barang WHERE idbarang=v_id FOR UPDATE;
            IF v_lock IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buku tidak ditemukan'; END IF;
            -- Form saat ini satu harga per buku per pengadaan. Tolak data ambigu.
            SELECT COUNT(*),SUM(jumlah),MIN(harga_satuan) INTO v_count,v_pesan,v_harga
            FROM detail_pengadaan WHERE idpengadaan=p_idpengadaan AND idbarang=v_id;
            IF v_count<>1 OR v_pesan IS NULL OR v_pesan<=0 OR v_harga IS NULL OR v_harga<0 THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Detail pengadaan buku tidak ditemukan atau ambigu';
            END IF;
            SELECT COALESCE(SUM(dp.jumlah_terima),0) INTO v_diterima
            FROM detail_penerimaan dp JOIN penerimaan p ON p.idpenerimaan=dp.idpenerimaan
            WHERE p.idpengadaan=p_idpengadaan AND dp.barang_idbarang=v_id;
            IF v_qty>v_pesan-v_diterima THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Jumlah terima melebihi sisa pesanan';
            END IF;
            IF v_qty*v_harga>2147483647 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Subtotal penerimaan melampaui INT'; END IF;
            -- Harga diambil dari pengadaan. Trigger menulis mutasi M satu kali.
            INSERT INTO detail_penerimaan(idpenerimaan,barang_idbarang,jumlah_terima,harga_satuan_terima,sub_total_terima)
            VALUES(p_idpenerimaan,v_id,v_qty,v_harga,v_qty*v_harga);
            SET v_i=v_i+1;
        END WHILE;
        IF NOT EXISTS (
            SELECT 1 FROM detail_pengadaan d WHERE d.idpengadaan=p_idpengadaan
            AND d.jumlah>(SELECT COALESCE(SUM(dp.jumlah_terima),0)
                FROM detail_penerimaan dp JOIN penerimaan p ON p.idpenerimaan=dp.idpenerimaan
                WHERE p.idpengadaan=p_idpengadaan AND dp.barang_idbarang=d.idbarang)
        ) THEN
            UPDATE pengadaan SET status='S' WHERE idpengadaan=p_idpengadaan;
        END IF;
        COMMIT;
    END;
END$$
DELIMITER ;
