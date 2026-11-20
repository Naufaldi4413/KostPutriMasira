-- Jalankan di phpMyAdmin (db_pengelolaan_kos).
-- 1) Perbaiki total sewa lama yang salah: tarif kamar per bulan x jumlah bulan (sewa sendiri).
UPDATE sewa s
JOIN kamar k ON k.id_kamar = s.id_kamar
SET s.total_sewa = k.harga * CAST(SUBSTRING_INDEX(s.periode, ' ', 1) AS UNSIGNED)
WHERE s.status = 'aktif'
  AND s.periode REGEXP '^[0-9]+ Bulan$'
  AND s.total_sewa = k.harga;
-- 2) Sinkronkan status kamar dengan jumlah sewa aktif & kapasitas.
UPDATE kamar k
SET k.status = CASE
  WHEN (SELECT COUNT(*) FROM sewa s WHERE s.id_kamar = k.id_kamar AND s.status = 'aktif') = 0 THEN 'kosong'
  WHEN (SELECT COUNT(*) FROM sewa s WHERE s.id_kamar = k.id_kamar AND s.status = 'aktif') < k.kapasitas THEN 'terisi'
  ELSE 'penuh' END;
-- 3) Perbaiki typo nama pengelola pada data awal.
UPDATE users SET nama = 'Pengelola' WHERE username = 'admin' AND nama = 'Pengelolaror';
