<?php
session_start();
require_once 'config.php';

// Cek login
if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true
) {
    header("Location: login.php");
    exit;
}

// Cek role
$role = $_SESSION['role'] ?? '';
$username_admin = $_SESSION['username'] ?? '';
$kategori_admin = $_SESSION['kategori'] ?? '';

if (!in_array($role, ['superadmin', 'admin'], true)) {
    die("Akses tidak diizinkan.");
}

if ($role === 'admin' && empty($kategori_admin)) {
    die("Kategori admin belum ditentukan.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin.php");
    exit;
}

$saran_id = (int) ($_POST['saran_id'] ?? 0);
$balasan = trim($_POST['balasan'] ?? '');

$current_page = max(1, (int) ($_POST['current_page'] ?? 1));
$search = $_POST['search'] ?? '';
$kategori = $_POST['kategori'] ?? '';

if ($saran_id <= 0 || $balasan === '') {
    die("ID saran atau balasan tidak valid.");
}

// Ambil kategori saran
$stmt_check = $conn->prepare("
    SELECT id, kategori
    FROM saran
    WHERE id = ?
");

$stmt_check->bind_param("i", $saran_id);
$stmt_check->execute();

$result_check = $stmt_check->get_result();

if ($result_check->num_rows !== 1) {
    $stmt_check->close();
    die("Data saran tidak ditemukan.");
}

$data_saran = $result_check->fetch_assoc();
$stmt_check->close();

// Admin kategori hanya boleh membalas kategori miliknya
if (
    $role === 'admin' &&
    $data_saran['kategori'] !== $kategori_admin
) {
    die("Anda tidak memiliki akses untuk membalas kategori ini.");
}

// Tentukan kategori pembalas
$kategori_pembalas = (
    $role === 'superadmin'
    ? 'Admin Utama'
    : $kategori_admin
);

// Simpan pesan admin ke tabel percakapan
$stmt_insert = $conn->prepare("
    INSERT INTO percakapan_saran
        (
            saran_id,
            pengirim_tipe,
            pengirim_nama,
            kategori_pembalas,
            isi_pesan
        )
    VALUES (?, 'admin', ?, ?, ?)
");

$stmt_insert->bind_param(
    "isss",
    $saran_id,
    $username_admin,
    $kategori_pembalas,
    $balasan
);

if (!$stmt_insert->execute()) {
    $stmt_insert->close();
    $conn->close();
    die("Balasan gagal disimpan.");
}

$stmt_insert->close();

// Status berubah karena admin mengirim pesan terakhir
$stmt_update = $conn->prepare("
    UPDATE saran
    SET status = 'Sudah Dibalas'
    WHERE id = ?
");

$stmt_update->bind_param("i", $saran_id);
$stmt_update->execute();
$stmt_update->close();

$conn->close();

// Kembali ke halaman admin dengan filter sebelumnya
$query_params = http_build_query([
    'page' => $current_page,
    'search' => $search,
    'kategori' => $kategori
]);

header("Location: admin.php?" . $query_params);
exit;
?>