<?php
session_start();
require_once 'config.php';

// ========================================
// CEK HAK AKSES SUPER ADMIN
// ========================================

if (
    !isset($_SESSION['loggedin']) ||
    $_SESSION['loggedin'] !== true ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'superadmin'
) {
    header("Location: admin.php");
    exit;
}

$message = '';
$error = '';

$kategori_valid = [
    'Umum',
    'Kepengurusan',
    'Akademik',
    'Pelatihan'
];

// ========================================
// PROSES TAMBAH, EDIT, DAN HAPUS ADMIN
// ========================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $action = $_POST['action'] ?? '';

    // ------------------------------------
    // TAMBAH ADMIN
    // ------------------------------------

    if ($action == 'add') {

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $kategori = $_POST['kategori'] ?? '';

        if (
            empty($username) ||
            empty($password) ||
            !in_array($kategori, $kategori_valid, true)
        ) {

            $error = "Semua data wajib diisi dengan benar.";

        } elseif (strlen($password) < 6) {

            $error = "Password minimal 6 karakter.";

        } else {

            // Cek username
            $stmt_check = $conn->prepare(
                "SELECT id FROM users WHERE username = ?"
            );

            $stmt_check->bind_param("s", $username);
            $stmt_check->execute();

            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {

                $error = "Username sudah digunakan.";

            } else {

                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    "INSERT INTO users (username, password, role, kategori)
                     VALUES (?, ?, 'admin', ?)"
                );

                $stmt->bind_param(
                    "sss",
                    $username,
                    $password_hash,
                    $kategori
                );

                if ($stmt->execute()) {
                    $message = "Admin berhasil ditambahkan.";
                } else {
                    $error = "Gagal menambahkan admin.";
                }

                $stmt->close();
            }

            $stmt_check->close();
        }
    }

    // ------------------------------------
    // EDIT ADMIN
    // ------------------------------------
    elseif ($action == 'edit') {

        $user_id = (int) ($_POST['user_id'] ?? 0);
        $kategori = $_POST['kategori'] ?? '';
        $password = $_POST['password'] ?? '';

        if (
            $user_id <= 0 ||
            !in_array($kategori, $kategori_valid, true)
        ) {

            $error = "Data admin tidak valid.";

        } else {

            // Pastikan akun yang diedit adalah admin biasa
            $stmt_check = $conn->prepare(
                "SELECT id FROM users
                 WHERE id = ? AND role = 'admin'"
            );

            $stmt_check->bind_param("i", $user_id);
            $stmt_check->execute();

            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows !== 1) {

                $error = "Admin tidak ditemukan.";

            } elseif (!empty($password) && strlen($password) < 6) {

                $error = "Password minimal 6 karakter.";

            } else {

                if (!empty($password)) {

                    $password_hash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $conn->prepare(
                        "UPDATE users
                         SET kategori = ?, password = ?
                         WHERE id = ? AND role = 'admin'"
                    );

                    $stmt->bind_param(
                        "ssi",
                        $kategori,
                        $password_hash,
                        $user_id
                    );

                } else {

                    $stmt = $conn->prepare(
                        "UPDATE users
                         SET kategori = ?
                         WHERE id = ? AND role = 'admin'"
                    );

                    $stmt->bind_param(
                        "si",
                        $kategori,
                        $user_id
                    );
                }

                if ($stmt->execute()) {
                    $message = "Data admin berhasil diperbarui.";
                } else {
                    $error = "Gagal memperbarui admin.";
                }

                $stmt->close();
            }

            $stmt_check->close();
        }
    }

    // ------------------------------------
    // HAPUS ADMIN
    // ------------------------------------
    elseif ($action == 'delete') {

        $user_id = (int) ($_POST['user_id'] ?? 0);

        if ($user_id <= 0 || $user_id == $_SESSION['user_id']) {

            $error = "Tidak dapat menghapus akun ini.";

        } else {

            $stmt = $conn->prepare(
                "DELETE FROM users
                 WHERE id = ? AND role = 'admin'"
            );

            $stmt->bind_param("i", $user_id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "Admin berhasil dihapus.";
            } else {
                $error = "Admin tidak ditemukan atau gagal dihapus.";
            }

            $stmt->close();
        }
    }
}

// ========================================
// AMBIL DATA ADMIN
// ========================================

$users_result = $conn->query(
    "SELECT id, username, role, kategori, created_at
     FROM users
     ORDER BY created_at DESC"
);

if (!$users_result) {
    die("Gagal mengambil data admin.");
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kotak Saran, Kritik dan Aspirasi</title>
    <link rel="stylesheet" href="./assets/style.css?v=2">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar">

        <div class="navbar-inner">

            <a href="index.php" class="nav-brand">
                <span class="nav-icon">
                    <i class="fa-regular fa-comments"></i>
                </span>
                Kotak Saran
            </a>

            <div>

                <a href="admin.php" class="nav-link">
                    Daftar Saran
                </a>

                <a href="manage_admins.php" class="nav-link active">
                    Manajemen Admin
                </a>

                <a href="logout.php" class="nav-link">
                    Logout
                </a>

                <img style="margin-right: 10px;" class="unipi" src="./assets/unipi.png" alt="" srcset="" width="60px"
                    height="60px">


            </div>

        </div>

    </nav>


    <main class="main-container">

        <div class="content-wrapper" style="max-width: 1000px;">

            <div class="content-header">

                <h1>Manajemen Admin</h1>

                <p>
                    Kelola akun admin berdasarkan kategori masing-masing.
                </p>

            </div>


            <!-- PESAN NOTIFIKASI -->

            <?php if ($message): ?>

                <p class="success" style="text-align:center; margin-bottom: 1.5rem;">

                    <?php echo htmlspecialchars($message); ?>

                </p>

            <?php endif; ?>


            <?php if ($error): ?>

                <p class="error" style="text-align:center; margin-bottom: 1.5rem;">

                    <?php echo htmlspecialchars($error); ?>

                </p>

            <?php endif; ?>


            <!-- FORM TAMBAH ADMIN -->

            <h2>Tambah Admin Baru</h2>

            <form action="manage_admins.php" method="POST" class="admin-form">

                <input type="hidden" name="action" value="add">


                <div class="form-group">

                    <label for="new_username">
                        Username
                    </label>

                    <input type="text" id="new_username" name="username" required>

                </div>


                <div class="form-group">

                    <label for="new_password">
                        Password
                    </label>

                    <input type="password" id="new_password" name="password" minlength="6" required>

                </div>


                <div class="form-group">

                    <label for="kategori">
                        Kategori Admin
                    </label>

                    <select name="kategori" id="kategori" required>

                        <option value="">
                            Pilih Kategori
                        </option>

                        <option value="Umum">
                            Umum
                        </option>

                        <option value="Kepengurusan">
                            Kepengurusan
                        </option>

                        <option value="Akademik">
                            Akademik
                        </option>

                        <option value="Pelatihan">
                            Pelatihan
                        </option>

                    </select>

                </div>


                <button type="submit">
                    Tambah Admin
                </button>

            </form>


            <!-- TABEL DAFTAR ADMIN -->

            <h2 style="margin-top: 3rem;">
                Daftar Admin
            </h2>


            <?php if ($users_result->num_rows > 0): ?>

                <table class="admin-table">

                    <thead>

                        <tr>

                            <th>No</th>

                            <th>Username</th>

                            <th>Role</th>

                            <th>Kategori</th>

                            <th>Dibuat Pada</th>

                            <th>Aksi</th>

                        </tr>

                    </thead>


                    <tbody>
                        <?= $no = 1; ?>

                        <?php while ($user = $users_result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?php echo $no++; ?>
                                </td>


                                <td>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </td>


                                <td>

                                    <?php if ($user['role'] == 'superadmin'): ?>

                                        <strong>Super Admin</strong>

                                    <?php else: ?>

                                        Admin

                                    <?php endif; ?>

                                </td>


                                <td>
                                    <?php echo htmlspecialchars($user['kategori'] ?? '-'); ?>
                                </td>


                                <td>
                                    <?php echo date(
                                        'd M Y H:i',
                                        strtotime($user['created_at'])
                                    ); ?>
                                </td>


                                <td>

                                    <?php if ($user['role'] == 'admin'): ?>

                                        <button type="button" class="btn-edit" onclick='showEditForm(
                                                <?php echo (int) $user["id"]; ?>,
                                                <?php echo json_encode($user["username"]); ?>,
                                                <?php echo json_encode($user["kategori"]); ?>
                                            )'>

                                            Edit

                                        </button>


                                        <form action="manage_admins.php" method="POST"
                                            style="display:inline-block; margin-left:5px;"
                                            onsubmit="return confirm('Yakin ingin menghapus admin ini?');">

                                            <input type="hidden" name="action" value="delete">


                                            <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">


                                            <button type="submit" class="btn-delete">

                                                Hapus

                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <span>Admin Utama</span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <p style="text-align:center;">
                    Belum ada admin yang terdaftar.
                </p>

            <?php endif; ?>


            <!-- MODAL EDIT ADMIN -->

            <div id="editAdminModal" class="modal" style="display:none;">

                <div class="modal-content">

                    <span class="close-button" onclick="hideEditForm()">

                        &times;

                    </span>


                    <h2>Edit Admin</h2>


                    <form action="manage_admins.php" method="POST" class="admin-form">

                        <input type="hidden" name="action" value="edit">


                        <input type="hidden" id="edit_user_id" name="user_id">


                        <div class="form-group">

                            <label for="edit_username">
                                Username
                            </label>

                            <input type="text" id="edit_username" readonly>

                        </div>


                        <div class="form-group">

                            <label for="edit_kategori">
                                Kategori Admin
                            </label>

                            <select name="kategori" id="edit_kategori" required>

                                <option value="Umum">
                                    Umum
                                </option>

                                <option value="Kepengurusan">
                                    Kepengurusan
                                </option>

                                <option value="Akademik">
                                    Akademik
                                </option>

                                <option value="Pelatihan">
                                    Pelatihan
                                </option>

                            </select>

                        </div>


                        <div class="form-group">

                            <label for="edit_password">
                                Password Baru
                            </label>

                            <input type="password" id="edit_password" name="password" minlength="6"
                                placeholder="Kosongkan jika tidak ingin mengubah">

                        </div>


                        <button type="submit">
                            Simpan Perubahan
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </main>


    <!-- JAVASCRIPT -->

    <script>

        function showEditForm(id, username, kategori) {

            document.getElementById('edit_user_id').value = id;

            document.getElementById('edit_username').value = username;

            document.getElementById('edit_kategori').value = kategori;

            document.getElementById('edit_password').value = '';

            document.getElementById('editAdminModal').style.display = 'block';

        }


        function hideEditForm() {

            document.getElementById('editAdminModal').style.display = 'none';

            document.getElementById('edit_password').value = '';

        }


        window.onclick = function (event) {

            let modal = document.getElementById('editAdminModal');

            if (event.target == modal) {

                hideEditForm();

            }

        }

    </script>

    <footer class="site-footer">
        <p>
            &copy; <?php echo date('Y'); ?>
            <span class="footer-name">Muhammad Candra Wijaya</span>
        </p>
        <span class="footer-caption">
            Kotak Saran, Kritik dan Aspirasi
        </span>
    </footer>
</body>

</html>