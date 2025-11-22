<nav class="navbar navbar-dark bg-danger">
  <div class="container-fluid">
        <div class="d-flex align-items-center">
            <a href="<?= BASE_URL; ?>">
                <img src="<?= BASE_URL; ?>assets/clk.png" alt="cendana logo" width="40" height="40" class="me-2">
            </a>
            <a class="fw-bold navbar-brand m-0 fs-5" href="<?= BASE_URL; ?>">PT Cendana Lintas Kargo</a>
        </div>
    <div class="d-lg-flex">
        <span class="navbar-text text-white me-3 d-none d-lg-block">
            <?= $_SESSION['username'];?>
        </span>
        <button class="navbar-toggler d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Toggle sidebar">
                <span class="navbar-toggler-icon"></span>
        </button>
    </div>
  </div>
</nav>