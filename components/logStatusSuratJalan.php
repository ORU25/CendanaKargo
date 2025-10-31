<div class="col-md-6">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
            <div class="d-flex align-items-center mb-4">
                <div class="bg-info bg-opacity-10 rounded p-2 me-3">
                    <i class="fa-solid fa-clock-rotate-left text-info"></i>
                </div>
                <h6 class="mb-0 fw-semibold">Timeline Perubahan Status</h6>
            </div>
            
            <?php if (empty($logs_sj)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fa-solid fa-inbox fa-2x mb-3 opacity-50"></i>
                    <p class="mb-0">Belum ada perubahan status yang tercatat</p>
                    <small>Log perubahan status akan muncul di sini</small>
                </div>
            <?php else: ?>
                <div class="position-relative">
                    <!-- Timeline Line -->
                    <div class="position-absolute top-0 start-0 h-100 border-start border-2 border-primary ms-3" style="opacity: 0.3;"></div>
                    
                    <?php foreach ($logs_sj as $index => $log): 
                        $statusColor = 'secondary';
                        switch(strtolower($log['status_baru'])) {
                            case 'draft':
                                $statusColor = 'warning';
                                break;
                            case 'dalam perjalanan':
                                $statusColor = 'primary';
                                break;
                            case 'sampai tujuan':
                                $statusColor = 'success';
                                break;
                            case 'dibatalkan':
                                $statusColor = 'danger';
                                break;
                        }
                        
                        $isLatest = ($index === 0);
                    ?>
                    <div class="d-flex align-items-start mb-4 position-relative">
                        <!-- Timeline Dot -->
                        <div class="flex-shrink-0 me-3">
                            <div class="rounded-circle bg-<?= $statusColor; ?> p-2 shadow-sm" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                <i class="fa-solid fa-<?= $isLatest ? 'circle-check' : 'circle' ?> text-white"></i>
                            </div>
                        </div>
                        
                        <!-- Timeline Content -->
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-1 fw-bold text-uppercase">
                                        <?php if ($log['status_lama']): ?>
                                            <span class="badge bg-secondary text-decoration-line-through me-1"><?= htmlspecialchars($log['status_lama']); ?></span>
                                            <i class="fa-solid fa-arrow-right fa-xs"></i>
                                        <?php endif; ?>
                                        <span class="badge bg-<?= $statusColor; ?> ms-1 mb-2"><?= htmlspecialchars($log['status_baru']); ?></span>
                                    </h6>
                                    <?php if ($log['keterangan']): ?>
                                        <p class="mb-1 text-muted small">
                                            <i class="fa-solid fa-comment-dots me-1"></i>
                                            <?= htmlspecialchars($log['keterangan']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isLatest): ?>
                                    <span class="badge bg-primary">Terakhir</span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted">
                                <i class="fa-solid fa-clock me-1"></i>
                                <?= date('d/m/Y H:i:s', strtotime($log['tanggal'])); ?>
                                <?php if ($log['username']): ?>
                                    <span class="mx-2">•</span>
                                    <i class="fa-solid fa-user me-1"></i>
                                    <?= htmlspecialchars($log['username']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
