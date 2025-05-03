<div class="modal-dialog modal-lg">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Edit User: <?php echo htmlspecialchars($user['name']); ?></h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <form action="users_actions.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
            
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Role</label>
                            <select class="form-control" name="role" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role; ?>" 
                                            <?php echo $user['role'] === $role ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($role); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Department</label>
                            <select class="form-control" name="department">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['name']; ?>"
                                            <?php echo $user['department'] === $dept['name'] ? 'selected' : ''; ?>>
                                        <?php echo $dept['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Contact Number</label>
                            <input type="text" class="form-control" name="contact_number"
                                   value="<?php echo htmlspecialchars($user['contact_number']); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Profile Image</label>
                            <?php if ($user['profile_image']): ?>
                                <div class="mb-2">
                                    <img src="<?php echo $user['profile_image']; ?>" 
                                         alt="Current Profile" style="max-width: 100px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control-file" name="profile_image" accept="image/*">
                            <small class="form-text text-muted">Leave empty to keep current image</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Permissions</label>
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
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" 
                                           id="edit_perm_<?php echo $permission['id']; ?>" 
                                           name="permissions[]" 
                                           value="<?php echo $permission['id']; ?>"
                                           <?php echo in_array($permission['id'], $userPermissionIds) ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="edit_perm_<?php echo $permission['id']; ?>">
                                        <i class="fas <?php echo $permission['icon']; ?> permission-icon"></i>
                                        <?php echo $permission['display_name']; ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div> 