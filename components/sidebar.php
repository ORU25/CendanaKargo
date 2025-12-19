<?php if(isset($page) && $_SESSION['role']): ?>
<div class="col-lg-2 d-none d-lg-block bg-white border-end min-vh-100 p-0">
    <div class="list-group list-group-flush custom-sidebar">
        <?php if($_SESSION['role'] == 'systemOwner'): ?>
            <a href="<?= BASE_URL; ?>dashboard/systemOwner/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'dashboard' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-gauge-high me-2"></i> Dashboard
            </a>
            <a href="<?= BASE_URL; ?>dashboard/systemOwner/pengiriman/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'pengiriman' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-truck-fast me-2"></i> Pengiriman
            </a>
            <a href="<?= BASE_URL; ?>dashboard/systemOwner/pelunasan/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'pelunasan' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-file-invoice-dollar me-2"></i> Pelunasan
            </a>
            <a href="<?= BASE_URL; ?>dashboard/systemOwner/surat_jalan/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'surat_jalan' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-file-lines me-2"></i> Surat Jalan
            </a>
            <a href="<?= BASE_URL; ?>dashboard/systemOwner/tarif/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'tarif' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-tags me-2"></i> Tarif
            </a>
            <a href="<?= BASE_URL; ?>dashboard/systemOwner/kantor_cabang/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'kantor_cabang' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-building me-2"></i> Kantor Cabang
            </a>
            <a href="<?= BASE_URL; ?>dashboard/systemOwner/driver/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'driver' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-car me-2"></i> Driver
            </a>
            <a href="<?= BASE_URL; ?>dashboard/systemOwner/user/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'user' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-user-gear me-2"></i> Pengguna
            </a>
            <a href="<?= BASE_URL; ?>dashboard/systemOwner/customisasi/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'customisasi' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-pen-to-square me-2"></i> Kustomisasi Web
            </a>

        <?php elseif($_SESSION['role'] == 'superAdmin'): ?>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'dashboard' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-gauge-high me-2"></i> Dashboard
            </a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'pengiriman' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-truck-fast me-2"></i> Pengiriman
            </a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/barang_masuk/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'barang_masuk' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-box-open me-2"></i> Barang Masuk
            </a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/pengambilan_barang/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'pengambilan_barang' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-box-archive me-2"></i> Pengambilan Barang
            </a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'surat_jalan' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-file-lines me-2"></i> Surat Jalan
            </a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'tarif' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-tags me-2"></i> Tarif
            </a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'user' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-user-gear me-2"></i> Pengguna
            </a>

        <?php elseif($_SESSION['role'] == 'admin'): ?>
            <a href="<?= BASE_URL; ?>dashboard/admin/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'dashboard' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-gauge-high me-2"></i> Dashboard
            </a>
            <a href="<?= BASE_URL; ?>dashboard/admin/pengiriman/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'pengiriman' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-truck-fast me-2"></i> Pengiriman
            </a>
            <a href="<?= BASE_URL; ?>dashboard/admin/barang_masuk/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'barang_masuk' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-box-open me-2"></i> Barang Masuk
            </a>
            <a href="<?= BASE_URL; ?>dashboard/admin/pengambilan_barang/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'pengambilan_barang' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-box-archive me-2"></i> Pengambilan Barang
            </a>
            <a href="<?= BASE_URL; ?>dashboard/admin/surat_jalan/" 
                class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-3 <?= $page == 'surat_jalan' ? 'active bg-danger text-white border-0' : ''; ?>">
                <i class="fa-solid fa-file-lines me-2"></i> Surat Jalan
            </a>
        <?php endif; ?>

    </div>

    <div class="text-start mt-3 mb-3 ms-3">
        <a href="<?= BASE_URL; ?>dashboard/change_password" class="btn btn-outline-secondary btn-sm w-60 rounded-2 px-3 fw-bold mb-2">
            <i class="fa-solid fa-key me-2"></i> Ubah Password
        </a>
        <a href="<?= BASE_URL; ?>auth/logout" class="btn btn-outline-danger btn-sm w-60 rounded-2 px-3 fw-bold">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </div>
</div>
<?php endif; ?>

<style>
.custom-sidebar .list-group-item {
    border: none;
    transition: all 0.2s ease;
    font-weight: 500;
}

.custom-sidebar .list-group-item:hover {
    background-color: #dc3545 !important;
    color: #fff !important;
}

.custom-sidebar .list-group-item:active,
.custom-sidebar .list-group-item.active {
    background-color: #b02a37 !important;
    color: #fff !important;
    font-weight: 600;
}

.custom-sidebar .list-group-item:hover i,
.custom-sidebar .list-group-item.active i {
    color: #fff !important;
}

.text-center .btn {
    width: 60%;
    font-weight: 500;
    border-width: 1.5px;
    transition: all 0.2s ease;
}

.text-center .btn:hover {
    background-color: #dc3545;
    color: #fff;
}
</style>
