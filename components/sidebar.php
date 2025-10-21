<?php if(isset($page)): ?>
<div class="col-lg-2 d-none d-lg-block bg-light border-end vh-100">
    <div class="list-group list-group-flush">
    <a href="<?= BASE_URL; ?>dashboard/superadmin/" class="list-group-item list-group-item-action <?= $page == 'dashboard' ? 'fw-bold text-danger' : ''; ?>">Dashboard</a>
    <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="list-group-item list-group-item-action <?= $page == 'pengiriman' ? 'fw-bold text-danger' : ''; ?>">Pengiriman</a>
    <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" class="list-group-item list-group-item-action <?= $page == 'surat_jalan' ? 'fw-bold text-danger' : ''; ?>">Surat Jalan</a>
    <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action <?= $page == 'tarif' ? 'fw-bold text-danger' : ''; ?>">Tarif</a>
    <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action <?= $page == 'kantor_cabang' ? 'fw-bold text-danger' : ''; ?>">Kantor Cabang</a>
    <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action <?= $page == 'user' ? 'fw-bold text-danger' : ''; ?>">User</a>
    </div>
    <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3">Logout</a>
</div>
<?php endif; ?>