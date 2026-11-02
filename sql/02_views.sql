-- 2. Tiga view total. Tidak menghapus atau mengubah data tabel.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_daftar_buku AS
SELECT b.idbarang AS id_buku, b.nama AS judul_buku, b.jenis AS kode_jenis,
       s.nama_satuan, b.harga AS harga_jual, b.status AS kode_status
FROM barang b LEFT JOIN satuan s ON b.idsatuan=s.idsatuan;

-- Grain: satu baris = satu detail penjualan, bukan satu struk.
-- ppn/total_nilai adalah nilai HEADER yang berulang. Agregasi per id_penjualan dahulu.
CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_laporan_penjualan AS
SELECT p.idpenjualan AS id_penjualan, dp.iddetail_penjualan AS id_detail,
       p.created_at AS tanggal, p.iduser AS id_user, u.username AS kasir,
       r.nama_role, dp.idbarang AS id_buku, b.nama AS judul_buku, s.nama_satuan,
       dp.jumlah, dp.harga_satuan, dp.subtotal, p.subtotal_nilai, p.ppn, p.total_nilai,
       p.idmargin_penjualan, m.persen AS margin_persen
FROM penjualan p
JOIN detail_penjualan dp ON dp.penjualan_idpenjualan=p.idpenjualan
LEFT JOIN barang b ON b.idbarang=dp.idbarang
LEFT JOIN satuan s ON s.idsatuan=b.idsatuan
LEFT JOIN `user` u ON u.iduser=p.iduser
LEFT JOIN role r ON r.idrole=u.idrole
LEFT JOIN margin_penjualan m ON m.idmargin_penjualan=p.idmargin_penjualan;

-- Saldo dihitung dari mutasi; jangan menjumlah kolom stock (saldo berjalan).
CREATE OR REPLACE SQL SECURITY INVOKER VIEW v_ringkasan_stok AS
SELECT b.idbarang AS id_buku, b.nama AS judul_buku, s.nama_satuan,
       COALESCE(k.total_masuk,0) AS total_masuk,
       COALESCE(k.total_keluar,0) AS total_keluar,
       k.sisa_stok, COALESCE(k.riwayat_count,0) AS riwayat_count,
       (COALESCE(k.riwayat_count,0)>0) AS tercatat,
       CASE WHEN k.riwayat_count IS NULL THEN 'Belum tercatat'
            WHEN k.sisa_stok<=0 THEN 'Habis'
            WHEN k.sisa_stok<=5 THEN 'Menipis' ELSE 'Aman' END AS status_stok
FROM barang b
LEFT JOIN satuan s ON s.idsatuan=b.idsatuan
LEFT JOIN (
    SELECT idbarang, SUM(COALESCE(masuk,0)) AS total_masuk,
           SUM(COALESCE(keluar,0)) AS total_keluar,
           SUM(COALESCE(masuk,0)-COALESCE(keluar,0)) AS sisa_stok,
           COUNT(*) AS riwayat_count
    FROM kartu_stok GROUP BY idbarang
) k ON k.idbarang=b.idbarang;
