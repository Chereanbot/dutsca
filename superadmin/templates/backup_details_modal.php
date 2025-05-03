<!-- Backup Details Modal -->
<style>
.modal-header {
    background-color: #00572d;
    color: white;
}

.modal-header .btn-close {
    color: white;
    filter: brightness(0) invert(1);
}

.detail-label {
    color: #333333;
    font-weight: 600;
    margin-bottom: 5px;
}

.detail-value {
    color: #666666;
    margin-bottom: 15px;
}

.status-badge {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.85em;
}

.status-completed { background-color: #1f9345; color: white; }
.status-pending { background-color: #f3c300; color: black; }
.status-failed { background-color: #dc3545; color: white; }
.status-deleted { background-color: #6c757d; color: white; }

.detail-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.detail-card i {
    color: #00572d;
    font-size: 20px;
    margin-right: 10px;
}
</style>

<div class="modal-content">
    <div class="modal-header">
        <h5 class="modal-title">Backup Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
        <div class="detail-card">
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-label"><i class="fas fa-fingerprint"></i> Backup ID</div>
                    <div class="detail-value"><?php echo htmlspecialchars($backup['id']); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="detail-label"><i class="fas fa-clock"></i> Created At</div>
                    <div class="detail-value"><?php echo date('Y-m-d H:i:s', strtotime($backup['created_at'])); ?></div>
                </div>
            </div>
        </div>

        <div class="detail-card">
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-label"><i class="fas fa-chart-pie"></i> Size</div>
                    <div class="detail-value"><?php echo formatSize($backup['size']); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="detail-label"><i class="fas fa-info-circle"></i> Status</div>
                    <div class="detail-value">
                        <span class="status-badge status-<?php echo $backup['status']; ?>">
                            <?php echo ucfirst($backup['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="detail-card">
            <div class="detail-label"><i class="fas fa-align-left"></i> Description</div>
            <div class="detail-value"><?php echo htmlspecialchars($backup['description'] ?? 'No description provided'); ?></div>
        </div>

        <div class="detail-card">
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-label"><i class="fas fa-user"></i> Created By</div>
                    <div class="detail-value"><?php echo htmlspecialchars($backup['creator_name'] ?? 'System'); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="detail-label"><i class="fas fa-compress-arrows-alt"></i> Compression</div>
                    <div class="detail-value">
                        <?php echo $backup['compressed'] ? '<i class="fas fa-check text-success"></i> Compressed' : '<i class="fas fa-times text-danger"></i> Not compressed'; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($backup['verify_at']): ?>
        <div class="detail-card">
            <div class="detail-label"><i class="fas fa-shield-alt"></i> Verification</div>
            <div class="detail-value">
                <div>Verified at: <?php echo date('Y-m-d H:i:s', strtotime($backup['verify_at'])); ?></div>
                <div>Result: <?php echo htmlspecialchars($backup['verify_result'] ?? 'N/A'); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="modal-footer">
        <?php if ($backup['status'] === 'completed'): ?>
        <button type="button" class="btn btn-primary" onclick="downloadBackup('<?php echo $backup['id']; ?>')">
            <i class="fas fa-download"></i> Download
        </button>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>
</div>

<?php
function formatSize($bytes) {
    if ($bytes === 0 || $bytes === null) return '0 Bytes';
    $k = 1024;
    $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB');
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?> 