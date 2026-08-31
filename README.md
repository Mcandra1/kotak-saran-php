# PETUNJUK KOTAK SARAN PHP

Aplikasi Kotak Saran, Kritik, dan Aspirasi berbasis PHP Native dan MySQL.

## Cara Menjalankan Project

1. Download repository dalam bentuk ZIP, lalu extract.

2. Pindahkan folder project `kotak-saran-php` ke:
   `C:\xampp\htdocs\`

3. Buka folder project `kotak-saran-php` menggunakan VS Code.

4. Jalankan XAMPP, kemudian nyalakan:
   - Apache
   - MySQL

5. Pada bagian MySQL di XAMPP, klik **Admin** untuk membuka phpMyAdmin.

6. Buat database baru dengan nama:
   `db_kotak_saran`

7. Pilih database `db_kotak_saran`, kemudian buka tab **SQL**.

8. Jalankan perintah SQL berikut:

```sql
CREATE TABLE saran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pesan TEXT NOT NULL,
    kategori VARCHAR(100) NOT NULL,
    kode_unik VARCHAR(100) NOT NULL UNIQUE,
    status VARCHAR(50) DEFAULT 'Belum Dibalas',
    balasan TEXT NULL,
    waktu_dibuat TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
);
```
9. Klik Kirim / Go untuk menjalankan SQL tersebut.

Perintah di atas akan membuat:

tabel saran untuk menyimpan saran, kritik, dan aspirasi.
tabel users untuk menyimpan akun admin.

10. Setelah tabel berhasil dibuat, buka file `seed.php` melalui browser untuk membuat akun admin:

   `http://localhost/kotak-saran-php/seed.php`

   File `seed.php` akan otomatis membuat akun admin jika akun tersebut belum tersedia.

11. Setelah menjalankan `seed.php`, gunakan akun berikut untuk login sebagai admin:

   **Username:** `admin`  
   **Password:** `admin123`

   > Jika ingin melihat atau mengetahui username dan password admin yang digunakan oleh project, dapat melihat file `seed.php`.

12. Setelah akun admin berhasil dibuat, demi keamanan **hapus file `seed.php` dari project**.

13. Buka halaman utama melalui:

   `http://localhost/kotak-saran-php/`

14. Untuk masuk sebagai admin, klik **Admin Login** dan gunakan akun yang telah dibuat.

15. Selesai. 🎉
