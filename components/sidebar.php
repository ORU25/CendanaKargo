<?php if(isset($page)): ?>
<div class="col-lg-2 d-none d-lg-block bg-white border-end vh-100 p-0">
    <div class="list-group list-group-flush custom-sidebar">

        <a href="<?= BASE_URL; ?>dashboard/superadmin/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'dashboard' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="fa-solid fa-gauge-high me-2"></i> Dashboard
        </a>

        <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'pengiriman' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="fa-solid fa-truck-fast me-2"></i> Pengiriman
        </a>

        <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'surat_jalan' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="fa-solid fa-file-lines me-2"></i> Surat Jalan
        </a>

        <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'tarif' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="fa-solid fa-tags me-2"></i> Tarif
        </a>

        <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'kantor_cabang' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="fa-solid fa-building me-2"></i> Kantor Cabang
        </a>

        <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'user' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="fa-solid fa-user-gear me-2"></i> User
        </a>

    </div>

    <div class="text-center mt-3 mb-3">
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger btn-sm w-60 rounded-2 px-3">
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
