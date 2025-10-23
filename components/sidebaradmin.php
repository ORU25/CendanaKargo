<?php if(isset($page)): ?>
<div class="col-lg-2 d-none d-lg-block bg-white border-end vh-100 p-0">
    <div class="list-group list-group-flush custom-sidebar">
        <a href="<?= BASE_URL; ?>dashboard/admin/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'dashboard' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="fa-solid fa-gauge-high me-2"></i> Dashboard
        </a>

        <a href="<?= BASE_URL; ?>dashboard/admin/pengiriman/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'pengiriman' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="fa-solid fa-truck-fast me-2"></i> Pengiriman
        </a>

        <a href="<?= BASE_URL; ?>dashboard/admin/surat_jalan/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'surat_jalan' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="fa-solid fa-file-lines me-2"></i> Surat Jalan
        </a>

        <a href="<?= BASE_URL; ?>dashboard/admin/barang_masuk/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'barang_masuk' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="fa-solid fa-box-open me-2"></i> Barang Masuk
        </a> 

        <!-- perlu perbaikan -->
        <a href="<?= BASE_URL; ?>dashboard/admin/barang_keluar/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'barang_keluar' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="fa-solid fa-boxes-packing me-2"></i> Barang Keluar
        </a>
    </div>

    <div class="text-center mt-3 mb-3">
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger btn-sm rounded-2 w-60 px-3 shadow-sm">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
        <!-- sampe sini -->
    </div>
</div>
<?php endif; ?>

<style>
/* ======== Hover & Active Sidebar ======== */
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

/* ======== Tombol Logout ======== */
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
