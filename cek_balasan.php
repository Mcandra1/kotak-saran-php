
<?php
session_start();
require_once 'config.php';

// ======================================
// AMBIL NOTIFIKASI SUKSES
// ======================================

$pesan_sukses = $_SESSION['pesan_sukses'] ?? '';
unset($_SESSION['pesan_sukses']);

// ======================================
// CEK STATUS LOGIN
// ======================================

$sudah_login = (
    isset($_SESSION['loggedin']) &&
    $_SESSION['loggedin'] === true
);

// ======================================
// INISIALISASI VARIABEL
// ======================================

$saran = null;
$percakapan = [];
$error = '';

$kode = trim($_GET['kode'] ?? '');

// ======================================
// CARI SARAN BERDASARKAN KODE UNIK
// ======================================

if ($kode !== '') {

    $stmt = $conn->prepare("
        SELECT *
        FROM saran
        WHERE kode_unik = ?
          AND is_deleted = 0
        LIMIT 1
    ");

    $stmt->bind_param("s", $kode);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $saran = $result->fetch_assoc();

        // ======================================
        // AMBIL RIWAYAT PERCAKAPAN
        // ======================================

        $stmt_chat = $conn->prepare("
            SELECT *
            FROM percakapan_saran
            WHERE saran_id = ?
            ORDER BY waktu_dibuat ASC, id ASC
        ");

        $stmt_chat->bind_param("i", $saran['id']);
        $stmt_chat->execute();

        $result_chat = $stmt_chat->get_result();

        while ($chat = $result_chat->fetch_assoc()) {
            $percakapan[] = $chat;
        }

        $stmt_chat->close();

    } else {

        $error = "Kode tidak ditemukan. Pastikan Anda memasukkan kode yang benar.";

    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Cek Balasan</title>

    <link rel="stylesheet" href="./assets/style.css">
</head>

<body>

<!-- ======================================
     NAVBAR
====================================== -->

<nav class="navbar">

    <div class="navbar-inner">

        <a href="/kotak-saran-php/index.php" class="nav-brand">
            Kotak Saran
        </a>

        <div>

            <a href="cek_balasan.php" class="nav-link active">
                Cek Balasan
            </a>

            <?php if (!$sudah_login): ?>

                <a href="login.php" class="nav-link">
                    Admin Login
                </a>

            <?php else: ?>

                <a href="admin.php" class="nav-link">
                    Dashboard Admin
                </a>

            <?php endif; ?>

        </div>

    </div>

</nav>

<!-- ======================================
     KONTEN UTAMA
====================================== -->

<main class="main-container">

    <div class="content-wrapper">

        <div class="content-header">

            <h1>Cek Status & Balasan</h1>

            <p>
                Masukkan kode unik yang Anda dapatkan setelah mengirim pesan.
            </p>

        </div>

        <!-- FORM PENCARIAN KODE -->

        <form method="GET" action="cek_balasan.php">

            <div class="form-group">

                <label for="kode">
                    Kode Unik Anda
                </label>

                <input
                    type="text"
                    id="kode"
                    name="kode"
                    value="<?php echo htmlspecialchars($kode); ?>"
                    autocomplete="off"
                    required
                >

            </div>

            <button type="submit">
                Cari Pesan
            </button>

        </form>
                <!-- NOTIFIKASI PESAN BERHASIL DIKIRIM -->
                <?php if ($pesan_sukses !== ''): ?>
                    <div style="
                        background:#dcfce7;
                        border:1px solid #86efac;
                        color:#166534;
                        padding:15px 18px;
                        border-radius:10px;
                        margin-top:15px;
                        text-align:center;
                    ">
                        <strong>
                            ✓ <?php echo htmlspecialchars($pesan_sukses); ?>
                        </strong>
                    </div>
                <?php endif; ?>


                <!-- NOTIFIKASI JIKA ADMIN SUDAH MEMBALAS -->
                <?php if ($saran && $saran['status'] === 'Sudah Dibalas'): ?>
                    <div style="
                        background:#dcfce7;
                        border:1px solid #86efac;
                        color:#166534;
                        padding:15px 18px;
                        border-radius:10px;
                        margin-top:15px;
                        text-align:center;
                    ">
                        <strong>Ada balasan dari admin untuk Anda! 🎉</strong>

                        <p style="margin:5px 0 0;">
                            Admin sudah memberikan tanggapan. Silakan lihat riwayat percakapan di bawah.
                        </p>
                    </div>
                <?php endif; ?>
        <!-- PESAN ERROR -->

        <?php if ($error !== ''): ?>

            <p class="error" style="text-align:center; margin-top:1.5rem;">
                <?php echo htmlspecialchars($error); ?>
            </p>

        <?php endif; ?>

        <!-- ======================================
             DETAIL SARAN
        ====================================== -->

        <?php if ($saran): ?>

            <div class="balasan-wrapper" style="margin-top:1rem;">

                <!-- PESAN AWAL -->

                <div class="saran-item">

                    <h3>Pesan Awal Anda</h3>

                    <p>
                        <?php
                        echo nl2br(
                            htmlspecialchars($saran['pesan'])
                        );
                        ?>
                    </p>

                    <div class="meta">

                        <span>
                            Kategori:
                            <b>
                                <?php echo htmlspecialchars($saran['kategori']); ?>
                            </b>
                        </span>

                        <span>
                            Diterima:

                            <?php
                            echo date(
                                'd M Y, H:i',
                                strtotime($saran['waktu_dibuat'])
                            );
                            ?>
                        </span>

                    </div>

                    <div class="meta" style="margin-top:8px;">

                        <span>
                            Status:
                            <b>
                                <?php echo htmlspecialchars($saran['status']); ?>
                            </b>
                        </span>

                    </div>

                </div>

                <!-- ======================================
                     RIWAYAT PERCAKAPAN
                ====================================== -->

                <div class="balasan-content" style="margin-top:20px;">

                    <h3>Riwayat Percakapan</h3>

                    <!-- BALASAN LAMA -->

                    <?php if (!empty($saran['balasan'])): ?>

                        <div style="
                            background:#f0f0f0;
                            padding:15px;
                            border-radius:8px;
                            margin-top:10px;
                        ">

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $saran['dibalas_oleh'] ?? 'Admin'
                                );
                                ?>

                                (Balasan sebelumnya)

                            </strong>

                            <p>
                                <?php
                                echo nl2br(
                                    htmlspecialchars($saran['balasan'])
                                );
                                ?>
                            </p>

                        </div>

                    <?php endif; ?>

                    <!-- BELUM ADA PERCAKAPAN -->

                    <?php if (
                        empty($percakapan) &&
                        empty($saran['balasan'])
                    ): ?>

                        <p class="no-reply">
                            Belum ada balasan. Silakan tunggu tanggapan admin.
                        </p>

                    <?php endif; ?>

                    <!-- TAMPILKAN SEMUA RIWAYAT -->

                    <?php foreach ($percakapan as $chat): ?>

                        <?php
                        $pesan_dari_admin = (
                            $chat['pengirim_tipe'] === 'admin'
                        );
                        ?>

                        <div style="
                            background:<?php echo $pesan_dari_admin ? '#f0f0f0' : '#e8f3ff'; ?>;
                            padding:15px;
                            border-radius:8px;
                            margin-top:12px;
                        ">

                            <strong>

                                <?php if ($pesan_dari_admin): ?>

                                    Admin

                                    <?php if (
                                        !empty($chat['kategori_pembalas']) &&
                                        $chat['kategori_pembalas'] !== 'Admin Utama'
                                    ): ?>

                                        <?php
                                        echo htmlspecialchars(
                                            $chat['kategori_pembalas']
                                        );
                                        ?>

                                    <?php elseif (
                                        ($chat['kategori_pembalas'] ?? '') === 'Admin Utama'
                                    ): ?>

                                        Utama

                                    <?php endif; ?>

                                <?php else: ?>

                                    <?php
                                    echo htmlspecialchars(
                                        $chat['pengirim_nama']
                                    );
                                    ?>

                                <?php endif; ?>

                            </strong>

                            <p>
                                <?php
                                echo nl2br(
                                    htmlspecialchars($chat['isi_pesan'])
                                );
                                ?>
                            </p>

                            <small style="color:#666;">

                                <?php
                                echo date(
                                    'd M Y, H:i',
                                    strtotime($chat['waktu_dibuat'])
                                );
                                ?>

                            </small>

                        </div>

                    <?php endforeach; ?>

                    <hr style="margin:20px 0;">

                    <!-- ======================================
                         FORM BALASAN USER
                    ====================================== -->

                    <?php if ($saran['status'] === 'Sudah Dibalas'): ?>

                        <h4>Kirim Balasan</h4>

                        <form action="proses_pesan_user.php" method="POST">

                            <input
                                type="hidden"
                                name="kode_unik"
                                value="<?php echo htmlspecialchars($saran['kode_unik']); ?>"
                            >

                            <div class="form-group">

                                <textarea
                                    name="isi_pesan"
                                    placeholder="Tulis pesan atau balasan Anda..."
                                    rows="4"
                                    required
                                ></textarea>

                            </div>

                            <button type="submit">
                                Kirim Pesan
                            </button>

                        </form>

                    <?php else: ?>

                        <p class="no-reply" style="
                            background:#fff3cd;
                            color:#856404;
                            padding:15px;
                            border-radius:8px;
                            margin-top:15px;
                        ">

                            <strong>
                                Menunggu Balasan Admin
                            </strong>

                            <br>

                            Pesan Anda sudah diterima. Silakan tunggu admin
                            memberikan balasan sebelum mengirim pesan berikutnya.

                        </p>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>

    </div>

</main>

</body>
</html>
