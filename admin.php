
<?php
session_start();
require_once 'config.php';

// ======================================
// PROSES LOGIN
// ======================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['username'], $_POST['password'])
) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("
        SELECT id, username, password, role, kategori
        FROM users
        WHERE username = ?
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result_user = $stmt->get_result();

    if ($result_user->num_rows === 1) {
        $user = $result_user->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['kategori'] = $user['kategori'];

            $stmt->close();

            header("Location: admin.php");
            exit;
        }
    }

    $stmt->close();

    header("Location: login.php?error=" . urlencode("Username atau password salah"));
    exit;
}

// ======================================
// CEK LOGIN DAN ROLE
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

if (!in_array($role, ['superadmin', 'admin'], true)) {
    session_destroy();
    header("Location: login.php?error=" . urlencode("Akses tidak diizinkan"));
    exit;
}

if ($role === 'admin' && empty($kategori_admin)) {
    die("Akun admin belum memiliki kategori. Hubungi Super Admin.");
}

// ======================================
// PENCARIAN DAN FILTER
// ======================================

$results_per_page = 5;

$current_page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $results_per_page;

$search_query = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['kategori'] ?? '');

$where_clauses = ["saran.is_deleted = 0"];
$params = [];
$types = '';

// ======================================
// FILTER PENCARIAN
// ======================================

if ($search_query !== '') {
    $where_clauses[] = "
        (
            LOWER(saran.pesan) LIKE LOWER(?)
            OR LOWER(COALESCE(saran.balasan, '')) LIKE LOWER(?)
            OR EXISTS (
                SELECT 1
                FROM percakapan_saran p
                WHERE p.saran_id = saran.id
                AND LOWER(p.isi_pesan) LIKE LOWER(?)
            )
        )
    ";

    $keyword = '%' . $search_query . '%';

    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;

    $types .= 'sss';
}

// ======================================
// FILTER KATEGORI BERDASARKAN ROLE
// ======================================

if ($role === 'superadmin') {
    if ($category_filter !== '') {
        $where_clauses[] = "saran.kategori = ?";
        $params[] = $category_filter;
        $types .= 's';
    }
} else {
    $where_clauses[] = "saran.kategori = ?";
    $params[] = $kategori_admin;
    $types .= 's';

    $category_filter = $kategori_admin;
}

// ======================================
// MEMBUAT WHERE CLAUSE
// ======================================

$sql_where = " WHERE " . implode(' AND ', $where_clauses);

// ======================================
// CEK SARAN ATAU PESAN BARU
// ======================================

$sql_notif_baru = "
    SELECT COUNT(*) AS total
    FROM saran
    WHERE is_deleted = 0
      AND status = 'Menunggu Balasan'
";

$params_notif = [];
$types_notif = '';

if ($role === 'admin') {
    $sql_notif_baru .= " AND kategori = ?";
    $params_notif[] = $kategori_admin;
    $types_notif .= 's';
}

$stmt_notif_baru = $conn->prepare($sql_notif_baru);

if (!empty($params_notif)) {
    $stmt_notif_baru->bind_param(
        $types_notif,
        ...$params_notif
    );
}

$stmt_notif_baru->execute();

$total_pesan_baru = (int) $stmt_notif_baru
    ->get_result()
    ->fetch_assoc()['total'];

$stmt_notif_baru->close();

// ======================================
// HITUNG TOTAL DATA
// ======================================

$total_result_sql = "
    SELECT COUNT(*) AS total
    FROM saran
    $sql_where
";

$stmt_total = $conn->prepare($total_result_sql);

if (!empty($params)) {
    $stmt_total->bind_param($types, ...$params);
}

$stmt_total->execute();

$total_results = (int) $stmt_total
    ->get_result()
    ->fetch_assoc()['total'];

$total_pages = (int) ceil($total_results / $results_per_page);

// ======================================
// AMBIL DATA SARAN
// ======================================

$data_sql = "
    SELECT *
    FROM saran
    $sql_where
    ORDER BY waktu_dibuat DESC
    LIMIT ? OFFSET ?
";

$data_params = $params;
$data_types = $types . 'ii';

$data_params[] = $results_per_page;
$data_params[] = $offset;

$stmt_data = $conn->prepare($data_sql);
$stmt_data->bind_param($data_types, ...$data_params);
$stmt_data->execute();

$result = $stmt_data->get_result();

// ======================================
// AMBIL NOTIFIKASI ADMIN
// ======================================

$stmt_notif = $conn->prepare("
    SELECT id, pesan_notifikasi, waktu_dibuat
    FROM notifikasi_admin
    WHERE user_id = ?
      AND dismissed_at IS NULL
    ORDER BY waktu_dibuat DESC
");

$stmt_notif->bind_param("i", $_SESSION['user_id']);
$stmt_notif->execute();

$result_notif = $stmt_notif->get_result();

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

    <!-- ====================================== -->
    <!-- NAVBAR -->
    <!-- ====================================== -->

    <nav class="navbar">
        <div class="navbar-inner">

            <a href="index.php" class="nav-brand">
                <span class="nav-icon">
                    <i class="fa-regular fa-comments"></i>
                </span>
                Kotak Saran
            </a>

            <div>
                <a href="admin.php" class="nav-link active">
                    Daftar Saran
                </a>

                <?php if ($role === 'superadmin'): ?>
                    <a href="manage_admins.php" class="nav-link">
                        Manajemen Admin
                    </a>
                <?php endif; ?>

                <a
                    href="logout.php"
                    class="nav-link"
                    onclick="return confirm('Apakah Anda yakin ingin logout?');">
                    Logout
                </a>

                <img class="unipi" src="./assets/unipi.png" alt="" srcset="" width="60px" height="60px">

            </div>

        </div>
    </nav>

    <main class="main-container">

        <div class="content-wrapper" style="max-width:900px;">

            <!-- ====================================== -->
            <!-- HEADER -->
            <!-- ====================================== -->

            <div class="content-header">

                <h1>Daftar Saran Masuk</h1>

                <p>
                    Login sebagai:

                    <b>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </b>

                    (
                    <?php
                    echo $role === 'superadmin'
                        ? 'Super Admin'
                        : 'Admin ' . htmlspecialchars($kategori_admin);
                    ?>
                    )
                </p>

            </div>

           

            <!-- ====================================== -->
            <!-- NOTIFIKASI PENGHAPUSAN -->
            <!-- ====================================== -->

            <?php if ($result_notif->num_rows > 0): ?>

                <div style="margin-bottom:20px;">

                    <h3>Notifikasi Aktivitas Admin</h3>

                    <?php while ($notif = $result_notif->fetch_assoc()): ?>

                        <div style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            gap:12px;
                            background:#fff3cd;
                            border:1px solid #ffe69c;
                            padding:12px 15px;
                            border-radius:8px;
                            margin-bottom:10px;
                        ">

                            <div>
                                <strong>
                                    Aktivitas Penghapusan Pesan
                                </strong>

                                <p style="margin:5px 0;">
                                    <?php
                                    echo htmlspecialchars($notif['pesan_notifikasi']);
                                    ?>
                                </p>

                                <small style="color:#666;">
                                    <?php
                                    echo date(
                                        'd M Y, H:i',
                                        strtotime($notif['waktu_dibuat'])
                                    );
                                    ?>
                                </small>
                            </div>

                            <form action="hapus_notifikasi.php" method="POST">

                                <input
                                    type="hidden"
                                    name="id_notifikasi"
                                    value="<?php echo (int) $notif['id']; ?>"
                                >

                                <button
                                    type="submit"
                                    title="Tutup notifikasi"
                                    style="
                                        border:none;
                                        background:transparent;
                                        font-size:24px;
                                        cursor:pointer;
                                        color:#555;
                                    "
                                >
                                    &times;
                                </button>

                            </form>

                        </div>

                    <?php endwhile; ?>

                </div>

            <?php endif; ?>

            <?php $stmt_notif->close(); ?>

            <!-- ====================================== -->
            <!-- FORM PENCARIAN DAN FILTER -->
            <!-- ====================================== -->

            <form method="GET" action="admin.php" class="filter-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Cari pesan atau balasan..."
                    autocomplete="on"
                    value="<?php echo htmlspecialchars($search_query); ?>"
                >

                <?php if ($role === 'superadmin'): ?>

                    <select name="kategori" onchange="this.form.submit()">

                        <option value="">Semua Kategori</option>

                        <?php
                        $daftar_kategori = [
                            'Umum',
                            'Kepengurusan',
                            'Akademik',
                            'Pelatihan'
                        ];
                        ?>

                        <?php foreach ($daftar_kategori as $kategori): ?>

                            <option
                                value="<?php echo htmlspecialchars($kategori); ?>"
                                <?php echo $category_filter === $kategori ? 'selected' : ''; ?>
                            >
                                <?php echo htmlspecialchars($kategori); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                <?php else: ?>

                    <input
                        type="text"
                        value="<?php echo htmlspecialchars($kategori_admin); ?>"
                        disabled
                    >

                <?php endif; ?>

                <button type="submit">
                    Cari
                </button>
                

            </form>
            <!-- ====================================== -->
            <!-- NOTIFIKASI SARAN / PESAN BARU -->
            <!-- ====================================== -->

            <?php if ($total_pesan_baru > 0): ?>

                <div style="
                    background:#dcfce7;
                    border:1px solid #86efac;
                    color:#166534;
                    padding:16px 20px;
                    border-radius:10px;
                    margin-bottom:20px;
                    display:flex;
                    justify-content:center;
                    align-items:center;
                    text-align:center;
                ">

                    <div>

                        <strong style="font-size:16px;">
                            Ada saran atau pesan baru nichh XD
                        </strong>

                        <p style="margin:5px 0 0; text-align:center;">
                            Ada
                            <b><?php echo $total_pesan_baru; ?></b>
                            pesan yang menunggu balasan admin.
                            Yuk, segera periksa!
                        </p>

                    </div>

                </div>

            <?php endif; ?>

            <!-- ====================================== -->
            <!-- DAFTAR SARAN -->
            <!-- ====================================== -->

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <div class="saran-item">

                        <!-- PESAN AWAL -->
                        <p>
                            <?php echo nl2br(htmlspecialchars($row['pesan'])); ?>
                        </p>

                        <!-- INFORMASI PESAN -->
                        <div class="meta">

                            <span>
                                Kategori:
                                <b>
                                    <?php echo htmlspecialchars($row['kategori']); ?>
                                </b>
                            </span>

                            <span class="status-badge status-<?php
                                echo htmlspecialchars(
                                    str_replace(' ', '-', $row['status'])
                                );
                            ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>

                        </div>

                        <div class="meta" style="margin-top:8px;">

                            <span>
                                Kode:
                                <code>
                                    <?php echo htmlspecialchars($row['kode_unik']); ?>
                                </code>
                            </span>

                            <span>
                                Diterima:
                                <?php
                                echo date(
                                    'd M Y, H:i',
                                    strtotime($row['waktu_dibuat'])
                                );
                                ?>
                            </span>

                        </div>

                        <!-- ====================================== -->
                        <!-- TOMBOL HAPUS PESAN -->
                        <!-- ====================================== -->

                        <?php if ($role === 'superadmin'): ?>

                            <form
                                action="hapus_saran.php"
                                method="POST"
                                onsubmit="return confirm('Yakin ingin menghapus pesan ini? Notifikasi akan dikirim kepada admin terkait.');"
                                style="margin-top:15px;"
                            >

                                <input
                                    type="hidden"
                                    name="saran_id"
                                    value="<?php echo (int) $row['id']; ?>"
                                >

                                <input
                                    type="hidden"
                                    name="current_page"
                                    value="<?php echo $current_page; ?>"
                                >

                                <input
                                    type="hidden"
                                    name="search"
                                    value="<?php echo htmlspecialchars($search_query); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="kategori"
                                    value="<?php echo htmlspecialchars($category_filter); ?>"
                                >

                                <button
                                    type="submit"
                                    style="
                                        background:#dc3545;
                                        color:white;
                                        border:none;
                                        padding:8px 15px;
                                        border-radius:5px;
                                        cursor:pointer;
                                    "
                                >
                                    Hapus Pesan
                                </button>

                            </form>

                        <?php endif; ?>

                        <!-- ====================================== -->
                        <!-- RIWAYAT PERCAKAPAN -->
                        <!-- ====================================== -->

                        <div class="reply-box">

                            <strong>Riwayat Percakapan:</strong>

                            <?php
                            $stmt_chat = $conn->prepare("
                                SELECT *
                                FROM percakapan_saran
                                WHERE saran_id = ?
                                ORDER BY waktu_dibuat ASC, id ASC
                            ");

                            $stmt_chat->bind_param("i", $row['id']);
                            $stmt_chat->execute();

                            $result_chat = $stmt_chat->get_result();

                            // BALASAN LAMA
                            if (!empty($row['balasan'])):
                            ?>

                                <div style="
                                    background:#f0f0f0;
                                    padding:12px;
                                    border-radius:8px;
                                    margin-top:10px;
                                ">

                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $row['dibalas_oleh'] ?? 'Admin'
                                        );
                                        ?>
                                        (Balasan sebelumnya)
                                    </strong>

                                    <p>
                                        <?php
                                        echo nl2br(
                                            htmlspecialchars($row['balasan'])
                                        );
                                        ?>
                                    </p>

                                </div>

                            <?php endif; ?>

                            <!-- PESAN DALAM THREAD -->
                            <?php while ($chat = $result_chat->fetch_assoc()): ?>

                                <?php
                                $dari_admin = $chat['pengirim_tipe'] === 'admin';

                                if ($dari_admin) {

                                    if ($role === 'superadmin') {

                                        $nama_tampil = (
                                            $chat['kategori_pembalas'] === 'Admin Utama'
                                            ? 'Anda (Admin Utama)'
                                            : 'Admin ' . $chat['kategori_pembalas']
                                        );

                                    } else {

                                        $nama_tampil = (
                                            $chat['pengirim_nama'] === ($_SESSION['username'] ?? '')
                                            ? 'Anda'
                                            : 'Admin ' . $chat['kategori_pembalas']
                                        );
                                    }

                                } else {

                                    $nama_tampil = 'Pengguna';
                                }
                                ?>

                                <div style="
                                    background:<?php echo $dari_admin ? '#f0f0f0' : '#e8f3ff'; ?>;
                                    padding:12px;
                                    border-radius:8px;
                                    margin-top:10px;
                                ">

                                    <strong>
                                        <?php echo htmlspecialchars($nama_tampil); ?>
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

                            <?php endwhile; ?>

                            <?php $stmt_chat->close(); ?>

                            <hr style="margin:20px 0;">

                            
                                <!-- FORM BALASAN ADMIN -->

                                <?php if (in_array($row['status'], ['Menunggu Balasan', 'Belum Dibalas'], true)): ?>

                                    <form action="proses_balasan.php" method="POST">

                                        <input
                                            type="hidden"
                                            name="saran_id"
                                            value="<?php echo (int) $row['id']; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="current_page"
                                            value="<?php echo $current_page; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="search"
                                            value="<?php echo htmlspecialchars($search_query); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="kategori"
                                            value="<?php echo htmlspecialchars($category_filter); ?>"
                                        >

                                        <textarea
                                            name="balasan"
                                            placeholder="Tulis balasan kepada pengguna..."
                                            required
                                        ></textarea>

                                        <button type="submit">
                                            Kirim Balasan
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <div style="
                                        background:#fff3cd;
                                        border:1px solid #ffe69c;
                                        color:#856404;
                                        padding:12px;
                                        border-radius:8px;
                                        margin-top:10px;
                                        text-align:center;
                                    ">
                                        <strong>Pesan sudah dibalas</strong>
                                    </div>

                                <?php endif; ?>

                        </div>

                    </div>

                <?php endwhile; ?>

                <!-- ====================================== -->
                <!-- PAGINATION -->
                <!-- ====================================== -->

                <?php if ($total_pages > 1): ?>

                    <div class="pagination">

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

                            <a
                                href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?>&kategori=<?php echo urlencode($category_filter); ?>"
                                class="<?php echo $i === $current_page ? 'active' : ''; ?>"
                            >
                                <?php echo $i; ?>
                            </a>

                        <?php endfor; ?>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <p style="text-align:center; margin-top:2rem;">
                    Tidak ada pesan yang cocok dengan kriteria Anda.
                </p>

            <?php endif; ?>

        </div>

    </main>
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

<?php
$stmt_total->close();
$stmt_data->close();
$conn->close();
?>
