# 📬 Aplikasi Kotak Saran, Kritik, dan Aspirasi

Aplikasi Kotak Saran, Kritik, dan Aspirasi berbasis **PHP Native dan MySQL**. Aplikasi ini dibuat untuk memudahkan pengguna dalam menyampaikan saran, kritik, maupun aspirasi serta memungkinkan admin memberikan tanggapan melalui sistem percakapan.

## ✨ Fitur Aplikasi

- Pengiriman saran, kritik, dan aspirasi.
- Kode unik untuk mengecek status dan riwayat pesan.
- Sistem percakapan dua arah antara pengguna dan admin.
- Balasan admin secara bergantian dengan pengguna.
- Manajemen akun admin.
- Pembagian hak akses berdasarkan role admin.
- Kategori admin: Umum, Kepengurusan, Akademik, dan Pelatihan.
- Super Admin dapat melihat dan mengelola seluruh kategori.
- Admin kategori hanya dapat mengelola saran sesuai kategorinya.
- Notifikasi pesan baru untuk admin.
- Notifikasi penghapusan saran untuk admin terkait.
- Penghapusan saran menggunakan sistem Soft Delete.
- Pencarian dan penyaringan saran.

## 🛠️ Teknologi yang Digunakan

- PHP Native
- MySQL
- HTML
- CSS
- JavaScript
- Bootstrap (jika digunakan pada tampilan project)

---

# 🚀 Cara Menjalankan Project

## 1. Download Project

1. Download repository ini dalam bentuk ZIP.
2. Extract file ZIP yang sudah diunduh.
3. Pindahkan folder `kotak-saran-php` ke dalam direktori:

   `C:\xampp\htdocs\`

4. Buka folder project `kotak-saran-php` menggunakan Visual Studio Code.

## 2. Jalankan XAMPP

Buka XAMPP Control Panel, kemudian aktifkan:

- Apache
- MySQL

Setelah keduanya aktif, buka phpMyAdmin dengan mengeklik tombol **Admin** pada bagian MySQL.

Atau buka melalui browser:

`http://localhost/phpmyadmin/`

## 3. Buat Database

Buat database baru dengan nama:

`db_kotak_saran`

Pilih database tersebut, kemudian buka tab **SQL**.

Jalankan seluruh perintah SQL berikut untuk membuat tabel yang dibutuhkan aplikasi.

## 4. Buat Struktur Tabel Database

### A. Tabel `users`

Tabel ini digunakan untuk menyimpan akun admin, termasuk role dan kategori masing-masing admin.

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    kategori VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

Keterangan:
- `superadmin` memiliki akses untuk mengelola seluruh kategori dan akun admin.
- `admin` hanya dapat mengelola saran sesuai kategori yang diberikan.
- Kategori admin meliputi Umum, Kepengurusan, Akademik, dan Pelatihan.

### B. Tabel `saran`

Tabel ini digunakan untuk menyimpan saran, kritik, aspirasi, serta informasi status dan balasan.

```sql
CREATE TABLE saran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pesan TEXT NOT NULL,
    kategori VARCHAR(100) NOT NULL,
    kode_unik VARCHAR(100) NOT NULL UNIQUE,
    status VARCHAR(50) DEFAULT 'Menunggu Balasan',
    balasan TEXT NULL,
    waktu_dibuat TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    deleted_by VARCHAR(100) DEFAULT NULL,
    deleted_role VARCHAR(20) DEFAULT NULL,
    deleted_kategori VARCHAR(50) DEFAULT NULL,
    deleted_at DATETIME DEFAULT NULL
);
```

Keterangan:
- `kode_unik` digunakan pengguna untuk mengecek pesan dan riwayat percakapan.
- `status` digunakan untuk mengetahui apakah pesan sedang menunggu balasan atau sudah dibalas.
- `is_deleted` digunakan untuk menandai saran yang dihapus tanpa langsung menghilangkan data aslinya.
- `deleted_by`, `deleted_role`, dan `deleted_kategori` menyimpan informasi admin yang menghapus saran.
- `deleted_at` menyimpan waktu penghapusan.

### C. Tabel `percakapan_saran`

Tabel ini digunakan untuk menyimpan seluruh riwayat percakapan antara pengguna dan admin.

```sql
CREATE TABLE percakapan_saran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    saran_id INT NOT NULL,
    pengirim_tipe ENUM('user', 'admin') NOT NULL,
    pengirim_nama VARCHAR(100) NOT NULL,
    kategori_pembalas VARCHAR(50) DEFAULT NULL,
    isi_pesan TEXT NOT NULL,
    waktu_dibuat TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (saran_id),
    CONSTRAINT fk_percakapan_saran
        FOREIGN KEY (saran_id)
        REFERENCES saran(id)
        ON DELETE CASCADE
);
```

Keterangan:
- Menyimpan pesan yang dikirim oleh pengguna maupun admin.
- Menyimpan nama pengirim dan kategori admin yang memberikan balasan.
- Menyimpan waktu setiap pesan dikirim.
- Mendukung percakapan dua arah secara bergantian.

### D. Tabel `notifikasi_admin`

Tabel ini digunakan untuk menyimpan notifikasi yang diterima oleh admin.

```sql
CREATE TABLE notifikasi_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    saran_id INT NOT NULL,
    pesan_notifikasi TEXT NOT NULL,
    waktu_dibuat TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dismissed_at DATETIME DEFAULT NULL,
    INDEX (user_id),
    INDEX (saran_id)
);
```

Keterangan:
- `user_id` menunjukkan admin yang menerima notifikasi.
- `saran_id` menunjukkan saran yang berkaitan dengan notifikasi.
- `pesan_notifikasi` berisi informasi notifikasi.
- `waktu_dibuat` menyimpan waktu notifikasi dibuat.
- `dismissed_at` menyimpan waktu ketika notifikasi dihilangkan dari tampilan.

Setelah semua perintah SQL berhasil dijalankan, database akan memiliki empat tabel utama:

| Nama Tabel | Fungsi |
|---|---|
| `users` | Menyimpan akun admin |
| `saran` | Menyimpan saran, kritik, dan aspirasi |
| `percakapan_saran` | Menyimpan riwayat percakapan |
| `notifikasi_admin` | Menyimpan notifikasi admin |

## 5. Konfigurasi Database

Buka file `config.php`, kemudian sesuaikan konfigurasi database dengan pengaturan MySQL di komputer masing-masing.

Contoh konfigurasi:

```php
<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_kotak_saran";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}
?>
```

Jika username, password, atau port MySQL berbeda, sesuaikan dengan konfigurasi XAMPP masing-masing.

## 6. Buat Akun Admin

Setelah database berhasil dibuat dan dikonfigurasi, buka file `seed.php` melalui browser:

`http://localhost/kotak-saran-php/seed.php`

File `seed.php` digunakan untuk membuat akun admin awal apabila akun tersebut belum tersedia.

Gunakan akun berikut untuk login apabila masih menggunakan konfigurasi akun bawaan project:

**Username:** `admin`  
**Password:** `admin123`

> Catatan: Username dan password dapat diperiksa melalui file `seed.php`. Pastikan akun bawaan diganti dengan kredensial yang aman.

Setelah proses pembuatan akun berhasil, demi keamanan **hapus file `seed.php` dari folder project**.

## 7. Jalankan Aplikasi

Buka halaman utama melalui browser:

`http://localhost/kotak-saran-php/`

Untuk masuk sebagai admin, klik menu **Admin Login**, kemudian masukkan username dan password yang sudah dibuat.

## 8. Selesai!

Aplikasi Kotak Saran, Kritik, dan Aspirasi sudah dapat digunakan.

---

# 💬 Alur Sistem Percakapan

Aplikasi ini menggunakan sistem percakapan dua arah secara bergantian.

1. Pengguna mengirimkan saran atau kritik.
2. Sistem memberikan kode unik kepada pengguna.
3. Admin menerima dan membalas pesan.
4. Pengguna membuka halaman pengecekan menggunakan kode unik.
5. Pengguna dapat membalas tanggapan admin.
6. Admin menerima notifikasi bahwa terdapat pesan baru.
7. Proses percakapan dapat dilakukan kembali secara bergantian.

### Status Pesan

| Status | Keterangan |
|---|---|
| Menunggu Balasan | Pesan menunggu tanggapan admin |
| Sudah Dibalas | Admin sudah memberikan tanggapan |

# 👥 Hak Akses Admin

| Role | Hak Akses |
|---|---|
| Super Admin | Mengelola seluruh saran, kategori, dan akun admin |
| Admin | Mengelola saran sesuai kategori yang diberikan |

# 📝 Catatan

- Pastikan Apache dan MySQL aktif sebelum menjalankan aplikasi.
- Pastikan seluruh tabel database sudah dibuat dengan struktur yang sesuai.
- Pastikan konfigurasi database pada `config.php` sudah benar.
- Jangan membagikan file `seed.php` yang masih aktif pada server publik.
- Untuk penggunaan di server publik, gunakan password admin yang kuat.
- Fitur Soft Delete membuat data saran tetap tersimpan di database meskipun sudah tidak ditampilkan pada halaman utama admin.

---

**Dibuat menggunakan PHP Native dan MySQL.**
