
<?php
session_start();
require_once 'config.php';

// Pastikan admin sudah login
if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true
) {
    header("Location: login.php");
    exit;
}

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin.php");
    exit;
}

$id_notifikasi = (int) ($_POST['id_notifikasi'] ?? 0);
$user_id = (int) $_SESSION['user_id'];

if ($id_notifikasi > 0) {

    // Hanya menghilangkan notifikasi milik akun yang sedang login
    $stmt = $conn->prepare("
        UPDATE notifikasi_admin
        SET dismissed_at = NOW()
        WHERE id = ?
          AND user_id = ?
          AND dismissed_at IS NULL
    ");

    $stmt->bind_param(
        "ii",
        $id_notifikasi,
        $user_id
    );

    $stmt->execute();
    $stmt->close();
}

$conn->close();

header("Location: admin.php");
exit;
