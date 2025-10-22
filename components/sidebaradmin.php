<?php 
if (!defined('BOOTSTRAP_ICONS_INCLUDED')): 
    define('BOOTSTRAP_ICONS_INCLUDED', true);
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';
endif;
?>

<?php if(isset($page)): ?>
<div class="col-lg-2 d-none d-lg-block bg-white border-end vh-100 p-0">
    <div class="list-group list-group-flush custom-sidebar">
        <a href="<?= BASE_URL; ?>dashboard/admin/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'dashboard' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
        <a href="<?= BASE_URL; ?>dashboard/admin/pengiriman/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'pengiriman' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="bi bi-truck me-2"></i> Pengiriman
        </a>
        <a href="<?= BASE_URL; ?>dashboard/admin/surat_jalan/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'surat_jalan' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="bi bi-file-earmark-text me-2"></i> Surat Jalan
        </a>
        <a href="<?= BASE_URL; ?>dashboard/admin/tarif/" 
           class="list-group-item list-group-item-action d-flex align-items-center rounded-0 px-3 py-2 <?= $page == 'tarif' ? 'active bg-danger text-white border-0' : ''; ?>">
           <i class="bi bi-cash-coin me-2"></i> Tarif
        </a>
    </div>

    <div class="text-center mt-3 mb-3">
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger w-75 rounded-0">
            <i class="bi bi-box-arrow-right me-2"></i> Logout
        </a>
    </div>
</div>
<?php endif; ?>

<style>
/* ======== Hover & Active Sidebar ======== */
.custom-sidebar .list-group-item {
    border: none;
    transition: all 0.2s ease;
}

/* Saat diarahkan (hover) */
.custom-sidebar .list-group-item:hover {
    background-color: #dc3545 !important;
    color: #fff !important;
}

/* Saat ditekan (active/focus) */
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
