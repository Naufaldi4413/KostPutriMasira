-- Jalankan di phpMyAdmin (db_pengelolaan_kos). Membersihkan data uji pada tabel kamar.
-- Aman: kamar yang sudah punya data sewa tidak dihapus (ditolak oleh foreign key bila ada).
DELETE FROM kamar WHERE nomor_kamar = 'dssdsd';
UPDATE kamar SET harga = 500000, keterangan = NULL WHERE nomor_kamar = 'A4';
UPDATE kamar SET keterangan = NULL WHERE nomor_kamar = 'B6';
UPDATE kamar SET keterangan = NULL WHERE keterangan = 'Kamar tersedia';
