-- 1. Dua stored function. Jalankan pada database yang dipilih, tanpa USE hardcoded.
-- Harga/subtotal disimpan sebagai rupiah bulat. Tarif PPN adalah parameter simulasi.
DELIMITER $$
CREATE FUNCTION fn_hitung_total_transaksi(p_subtotal BIGINT, p_ppn_persen DECIMAL(5,2))
RETURNS BIGINT
DETERMINISTIC
NO SQL
SQL SECURITY INVOKER
BEGIN
    IF p_subtotal IS NULL OR p_subtotal < 0 OR p_ppn_persen IS NULL OR p_ppn_persen < 0 OR p_ppn_persen > 100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Subtotal/PPN tidak valid';
    END IF;
    RETURN p_subtotal + ROUND(p_subtotal * p_ppn_persen / 100, 0);
END$$

CREATE FUNCTION fn_hitung_stok(p_idbarang INT)
RETURNS BIGINT
NOT DETERMINISTIC
READS SQL DATA
SQL SECURITY INVOKER
BEGIN
    DECLARE v_stok BIGINT;
    -- SUM menghasilkan NULL bila belum ada riwayat: bedakan dari stok habis (0).
    SELECT SUM(COALESCE(masuk,0) - COALESCE(keluar,0)) INTO v_stok
    FROM kartu_stok WHERE idbarang = p_idbarang;
    RETURN v_stok;
END$$
DELIMITER ;
