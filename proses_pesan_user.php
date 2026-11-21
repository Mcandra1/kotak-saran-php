
<?php
session_start();
require_once 'config.php';

// ======================================
// CEK METODE REQUEST
// ======================================


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cek_balasan.php");
    exit;
}

// ======================================
// AMBIL DATA POST
// ======================================

$kode = trim($_POST['kode_unik'] ?? '');
$isi_pesan = trim($_POST['isi_pesan'] ?? '');

if ($kode === '' || $isi_pesan === '') {
    die("Kode atau pesan tidak boleh kosong.");
}

// ======================================
// CEK DATA SARAN DAN STATUS
// ======================================

$stmt = $conn->prepare("
    SELECT id, status
    FROM saran
    WHERE kode_unik = ?
      AND is_deleted = 0
    LIMIT 1
");

$stmt->bind_param("s", $kode);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    $conn->close();

    die("Data saran tidak ditemukan.");
}

$data_saran = $result->fetch_assoc();

$saran_id = (int) $data_saran['id'];
$status = $data_saran['status'];

$stmt->close();

// ======================================
// CEK APAKAH ADMIN SUDAH MEMBALAS
// ======================================

if ($status !== 'Sudah Dibalas') {
    $conn->close();

    echo "<script>
        alert('Pesan Anda belum dibalas oleh admin. Silakan tunggu balasan terlebih dahulu.');
        window.location.href = 'cek_balasan.php?kode=" . urlencode($kode) . "';
    </script>";

    exit;
}

// ======================================
// SIMPAN PESAN PENGGUNA
// ======================================

$pengirim_nama = 'Pengguna';

$stmt_insert = $conn->prepare("
    INSERT INTO percakapan_saran
        (saran_id, pengirim_tipe, pengirim_nama, isi_pesan)
    VALUES (?, 'user', ?, ?)
");

$stmt_insert->bind_param(
    "iss",
    $saran_id,
    $pengirim_nama,
    $isi_pesan
);

if ($stmt_insert->execute()) {

    // ======================================
    // UBAH STATUS MENJADI MENUNGGU BALASAN
    // ======================================

    $stmt_update = $conn->prepare("
        UPDATE saran
        SET status = 'Menunggu Balasan'
        WHERE id = ?
    ");

    $stmt_update->bind_param("i", $saran_id);

    if (!$stmt_update->execute()) {
        $stmt_update->close();
        $stmt_insert->close();
        $conn->close();

        die("Pesan tersimpan, tetapi status gagal diperbarui.");
    }

    $stmt_update->close();
    $stmt_insert->close();

    $_SESSION['pesan_sukses'] = "Pesan berhasil dikirim! Silakan tunggu balasan dari admin.";
    
    $conn->close();


    header(
        "Location: cek_balasan.php?kode=" . urlencode($kode)
    );

    exit;
}

// ======================================
// JIKA GAGAL
// ======================================

$stmt_insert->close();
$conn->close();

die("Pesan gagal dikirim.");

?>
