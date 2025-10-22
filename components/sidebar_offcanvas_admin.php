<?php if(isset($page)): ?>
<nav class="d-lg-none bg-white border-bottom px-3 py-2 d-flex justify-content-between align-items-center">
    <button class="btn btn-outline-danger" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
        <i class="fa-solid fa-bars"></i>
    </button>
    <span class="fw-bold">Menu</span>
</nav>

<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold" id="mobileSidebarLabel">Menu Navigasi</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body p-0">
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

            <a href="<?= BASE_URL; ?>dashboard/admin/barang_keluar/" 
               class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'barang_keluar' ? 'active bg-danger text-white border-0' : ''; ?>">
               <i class="fa-solid fa-boxes-packing me-2"></i> Barang Keluar
            </a>
        </div>

        <div class="text-center mt-3 mb-3">
            <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger w-75 rounded-0">
                <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Hover & Active untuk Mobile Sidebar */
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
</style>
