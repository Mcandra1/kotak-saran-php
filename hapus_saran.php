
<?php
session_start();
require_once 'config.php';

// ======================================
// CEK LOGIN
// ======================================

if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true
) {
    header("Location: login.php");
    exit;
}

$role = $_SESSION['role'] ?? '';
$kategori_admin = $_SESSION['kategori'] ?? '';
$username = $_SESSION['username'] ?? '';

if (!in_array($role, ['superadmin', 'admin'], true)) {
    die("Akses tidak diizinkan.");
}

if ($role === 'admin' && empty($kategori_admin)) {
    die("Akun admin belum memiliki kategori.");
}

// ======================================
// CEK REQUEST
// ======================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin.php");
    exit;
}

$saran_id = (int) ($_POST['saran_id'] ?? 0);
$current_page = max(1, (int) ($_POST['current_page'] ?? 1));

$search = trim($_POST['search'] ?? '');
$kategori_filter = trim($_POST['kategori'] ?? '');

if ($saran_id <= 0) {
    die("ID pesan tidak valid.");
}

// ======================================
// PROSES PENGHAPUSAN
// ======================================

try {

    $conn->begin_transaction();

    // Ambil data pesan
    $stmt = $conn->prepare("
        SELECT id, kategori
        FROM saran
        WHERE id = ?
          AND is_deleted = 0
        FOR UPDATE
    ");

    $stmt->bind_param("i", $saran_id);
    $stmt->execute();

    $saran = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$saran) {
        throw new Exception("Pesan tidak ditemukan atau sudah dihapus.");
    }

    $kategori_pesan = $saran['kategori'];

    // Admin kategori hanya boleh menghapus kategori miliknya
    if (
        $role === 'admin' &&
        $kategori_pesan !== $kategori_admin
    ) {
        throw new Exception("Anda tidak memiliki izin menghapus pesan ini.");
    }

    // Identitas admin yang menghapus
    $deleted_role = $role;

    $deleted_kategori = ($role === 'superadmin')
        ? 'Admin Utama'
        : $kategori_admin;

    // Soft delete pesan
    $stmt = $conn->prepare("
        UPDATE saran
        SET
            is_deleted = 1,
            deleted_by = ?,
            deleted_role = ?,
            deleted_kategori = ?,
            deleted_at = NOW()
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssi",
        $username,
        $deleted_role,
        $deleted_kategori,
        $saran_id
    );

    $stmt->execute();
    $stmt->close();

    // ======================================
    // KIRIM NOTIFIKASI KE ADMIN TERKAIT
    // ======================================

    $stmt_admin = $conn->prepare("
        SELECT id
        FROM users
        WHERE role = 'superadmin'
           OR (
                role = 'admin'
                AND kategori = ?
           )
    ");

    $stmt_admin->bind_param("s", $kategori_pesan);
    $stmt_admin->execute();

    $result_admin = $stmt_admin->get_result();

    if ($role === 'superadmin') {
        $nama_penghapus = "Super Admin ($username)";
    } else {
        $nama_penghapus = "Admin $kategori_admin ($username)";
    }

    $isi_notifikasi =
        "Pesan kategori $kategori_pesan dihapus oleh $nama_penghapus.";

    $stmt_notif = $conn->prepare("
        INSERT INTO notifikasi_admin
            (user_id, saran_id, pesan_notifikasi)
        VALUES (?, ?, ?)
    ");

    while ($admin = $result_admin->fetch_assoc()) {

        $user_id = (int) $admin['id'];

        $stmt_notif->bind_param(
            "iis",
            $user_id,
            $saran_id,
            $isi_notifikasi
        );

        $stmt_notif->execute();
    }

    $stmt_notif->close();
    $stmt_admin->close();

    // Simpan seluruh perubahan
    $conn->commit();

    // ======================================
    // KEMBALI KE ADMIN.PHP
    // ======================================

    header(
        "Location: admin.php?page=" . $current_page .
        "&search=" . urlencode($search) .
        "&kategori=" . urlencode($kategori_filter) .
        "&notif=deleted"
    );

    exit;

} catch (Throwable $e) {

    $conn->rollback();

    die(
        "Gagal menghapus pesan: " .
        htmlspecialchars($e->getMessage())
    );
}
