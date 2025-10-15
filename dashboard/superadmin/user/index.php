<?php
    session_start();
    if(!isset($_SESSION['username'])){
        header("Location: ../../../auth/login.php");
        exit;
    }

    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin'){
        header("Location: ../../../?error=unauthorized");
        exit;
    }

    include '../../../config/database.php';
    $title = "Dashboard - Cendana Kargo";
    
    $sql = "SELECT u.id, u.username, u.role, c.kode_cabang 
        FROM user AS u 
        LEFT JOIN kantor_cabang AS c ON u.id_cabang = c.id 
        ORDER BY u.id ASC";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $users = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $users = [];
    }
?>

<?php
    $page = "user";
    include '../../../templates/header.php';
    include '../../../components/navDashboard.php';
    include '../../../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
  <div class="row">
    <div class="col-lg-2 d-none d-lg-block bg-light border-end vh-100">
      <div class="list-group list-group-flush">
        <a href="<?= BASE_URL; ?>dashboard/superadmin/" class="list-group-item list-group-item-action">Dashboard</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="list-group-item list-group-item-action">Pengiriman</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" class="list-group-item list-group-item-action">Surat Jalan</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action">Tarif</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action">Kantor Cabang</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action fw-bold text-danger">User</a>
      </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3">Logout</a>
    </div>

    <!-- Konten utama -->
    <div class="col-lg-10">
        <?php if(isset($_GET['success']) && $_GET['success'] == 'created'){
            $type = "success";
            $message = "User berhasil ditambahkan";
            include '../../../components/alert.php';
        }?>
        <h1>User</h1>
        <a href="create" class="btn btn-success mb-3">Add New User</a>
        <table class="table">
            <thead>
                <tr>
                <th scope="col">id</th>
                <th scope="col">Username</th>
                <th scope="col">Role</th>
                <th scope="col">Kode Cabang</th>
                <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $index => $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['id']); ?></td>
                    <td><?= htmlspecialchars($user['username']); ?></td>
                    <td><?= htmlspecialchars($user['role']); ?></td>
                    <td><?= htmlspecialchars($user['kode_cabang'] ?? '-'); ?></td>
                    <td>
                        <a href="update?id=<?= $user['id']; ?>" class="btn btn-sm btn-primary <?= $_SESSION['user_id'] === $user['id'] ? 'disabled' : ''; ?>">Edit</a>
                        <a href="delete?id=<?= $user['id']; ?>" class="btn btn-sm btn-danger <?= $_SESSION['user_id'] === $user['id'] ? 'disabled' : ''; ?>">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
  </div>
</div>
<?php
    include '../../../templates/footer.php';
?>