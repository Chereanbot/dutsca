<?php if (empty($permissions)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> This user has no permissions assigned.
    </div>
<?php else: ?>
    <div class="permission-list">
        <?php 
        $currentCategory = '';
        foreach ($permissions as $permission):
            if ($currentCategory !== $permission['category_name']):
                $currentCategory = $permission['category_name'];
        ?>
            <div class="permission-category">
                <i class="fas fa-folder"></i> <?php echo $currentCategory; ?>
            </div>
        <?php endif; ?>
            <div class="permission-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas <?php echo $permission['icon']; ?> permission-icon"></i>
                        <?php echo $permission['display_name']; ?>
                    </div>
                    <small class="text-muted">
                        Granted by <?php echo htmlspecialchars($permission['granted_by_name']); ?>
                        on <?php echo date('Y-m-d H:i', strtotime($permission['granted_at'])); ?>
                    </small>
                </div>
                <?php if ($permission['description']): ?>
                    <small class="text-muted d-block mt-1">
                        <?php echo htmlspecialchars($permission['description']); ?>
                    </small>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?> 