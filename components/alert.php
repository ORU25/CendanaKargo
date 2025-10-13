<?php if(isset($type) && isset($message)): ?>  
    <div class="alert alert-<?= $type; ?> alert-dismissible fade show mt-3 w-100" role="alert">
        <?= $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
