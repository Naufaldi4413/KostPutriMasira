-- Jalankan di phpMyAdmin (database db_pengelolaan_kos) untuk menyamakan fasilitas dengan data pemilik kost.
-- Semua fasilitas dibuat jenis 'umum' (sama untuk semua kamar).
DELETE FROM kamar_fasilitas;
DELETE FROM fasilitas;
INSERT INTO fasilitas (id_fasilitas, nama_fasilitas, jenis, keterangan) VALUES
(1, 'Kasur (2 buah)',     'umum', 'Dua kasur di setiap kamar.'),
(2, 'Lemari Besar',       'umum', 'Lemari besar dengan 2 pintu.'),
(3, 'Dapur Bersama',      'umum', 'Dapur untuk dipakai bersama penghuni.'),
(4, 'Kamar Mandi Luar',   'umum', 'Kamar mandi di luar kamar, dipakai bersama.'),
(5, 'Kulkas Besar',       'umum', 'Kulkas besar untuk dipakai bersama.'),
(6, 'Parkiran',           'umum', 'Area parkir untuk penghuni.'),
(7, 'Tempat Jemuran',     'umum', 'Tempat menjemur pakaian bersama.'),
(8, 'WiFi',               'umum', 'Internet untuk penghuni.');
