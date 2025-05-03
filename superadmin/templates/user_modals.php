<?php
// Prevent direct access
if (!defined('INCLUDED_FROM_USERS')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access forbidden.');
}
?>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 text-center mb-4">
                        <div class="user-profile-image">
                            <img src="" alt="Profile" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                            <h4 class="user-name mb-1"></h4>
                            <p class="text-muted user-role mb-2"></p>
                            <div class="user-status"></div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="user-info">
                            <h5 class="border-bottom pb-2">Account Information</h5>
                            <div class="row mb-3">
                                <div class="col-sm-4 text-muted">Email</div>
                                <div class="col-sm-8 user-email"></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-4 text-muted">Department</div>
                                <div class="col-sm-8 user-department"></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-4 text-muted">Contact</div>
                                <div class="col-sm-8 user-contact"></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-4 text-muted">Last Login</div>
                                <div class="col-sm-8 user-last-login"></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-sm-4 text-muted">Account Created</div>
                                <div class="col-sm-8 user-created"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-4">
                    <div class="col-12">
                        <h5 class="border-bottom pb-2">User Activity</h5>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3 class="user-logins mb-1">0</h3>
                                        <small class="text-muted">Total Logins</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3 class="user-permissions mb-1">0</h3>
                                        <small class="text-muted">Permissions</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3 class="user-actions mb-1">0</h3>
                                        <small class="text-muted">Actions</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h3 class="user-days-active mb-1">0</h3>
                                        <small class="text-muted">Days Active</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary edit-user-btn">
                    <i class="fas fa-edit mr-1"></i> Edit User
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="editUserForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <div class="edit-profile-image">
                                <img src="" alt="Profile" class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                                <div class="mt-2">
                                    <label class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-camera mr-1"></i> Change Photo
                                        <input type="file" name="profile_image" class="d-none" accept="image/*">
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Role</label>
                                        <select class="form-control" name="role" required>
                                            <?php foreach ($roles as $role => $description): ?>
                                                <option value="<?php echo $role; ?>" data-description="<?php echo $description; ?>">
                                                    <?php echo ucfirst($role); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted role-description"></small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Department</label>
                                        <select class="form-control" name="department">
                                            <option value="">Select Department</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo $dept['name']; ?>"><?php echo $dept['name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" class="form-control" name="contact_number">
                            </div>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5 class="border-bottom pb-2">User Permissions</h5>
                            <div class="permission-list">
                                <?php 
                                $currentCategory = '';
                                foreach ($permissions as $permission):
                                    if ($currentCategory !== $permission['category_name']):
                                        $currentCategory = $permission['category_name'];
                                ?>
                                    <div class="permission-category">
                                        <i class="fas <?php echo $permission['category_icon']; ?>"></i>
                                        <?php echo $currentCategory; ?>
                                        <small class="text-muted"><?php echo $permission['category_description']; ?></small>
                                    </div>
                                <?php endif; ?>
                                    <div class="permission-item">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" 
                                                   id="edit_perm_<?php echo $permission['id']; ?>" 
                                                   name="permissions[]" 
                                                   value="<?php echo $permission['id']; ?>">
                                            <label class="custom-control-label" for="edit_perm_<?php echo $permission['id']; ?>">
                                                <i class="fas <?php echo $permission['icon']; ?> permission-icon"></i>
                                                <?php echo $permission['display_name']; ?>
                                                <small class="text-muted d-block"><?php echo $permission['description']; ?></small>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- User Activity Logs Modal -->
<div class="modal fade" id="userLogsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Activity History</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="activity-filters mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <select class="form-control" id="activityTypeFilter">
                                <option value="">All Activities</option>
                                <option value="login">Logins</option>
                                <option value="permission">Permission Changes</option>
                                <option value="profile">Profile Updates</option>
                                <option value="security">Security Events</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="date" class="form-control" id="activityDateFilter">
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" class="form-control" id="activitySearch" placeholder="Search logs...">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="userLogsTableBody">
                            <!-- Populated via JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3" id="loadMoreLogs" style="display: none;">
                    <button class="btn btn-outline-primary">
                        <i class="fas fa-sync-alt mr-1"></i> Load More
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="exportUserLogs()">
                    <i class="fas fa-file-export mr-1"></i> Export Logs
                </button>
            </div>
        </div>
    </div>
</div>

<!-- User Settings Modal -->
<div class="modal fade" id="userSettingsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Security Settings</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="userSettingsForm">
                <div class="modal-body">
                    <div class="security-section mb-4">
                        <h6 class="border-bottom pb-2">Authentication</h6>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="twoFactorEnabled" name="two_factor_enabled">
                                <label class="custom-control-label" for="twoFactorEnabled">Two-Factor Authentication</label>
                            </div>
                            <small class="form-text text-muted">Require verification code on login</small>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="loginNotification" name="login_notification">
                                <label class="custom-control-label" for="loginNotification">Login Notifications</label>
                            </div>
                            <small class="form-text text-muted">Send email alerts for new login attempts</small>
                        </div>
                    </div>
                    
                    <div class="security-section mb-4">
                        <h6 class="border-bottom pb-2">Account Protection</h6>
                        <div class="form-group">
                            <label>Failed Login Attempts</label>
                            <select class="form-control" name="account_lockout_threshold">
                                <option value="3">3 attempts</option>
                                <option value="5">5 attempts</option>
                                <option value="10">10 attempts</option>
                            </select>
                            <small class="form-text text-muted">Lock account after specified failed attempts</small>
                        </div>
                        <div class="form-group">
                            <label>Session Timeout</label>
                            <select class="form-control" name="session_timeout">
                                <option value="15">15 minutes</option>
                                <option value="30">30 minutes</option>
                                <option value="60">1 hour</option>
                                <option value="120">2 hours</option>
                            </select>
                            <small class="form-text text-muted">Automatically log out after inactivity</small>
                        </div>
                    </div>

                    <div class="security-section">
                        <h6 class="border-bottom pb-2">Access Restrictions</h6>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="ipRestriction" name="ip_restriction">
                                <label class="custom-control-label" for="ipRestriction">IP Address Restriction</label>
                            </div>
                            <small class="form-text text-muted">Limit access to specific IP addresses</small>
                        </div>
                        <div class="form-group ip-whitelist" style="display: none;">
                            <label>Allowed IP Addresses</label>
                            <textarea class="form-control" name="ip_whitelist" rows="3" placeholder="Enter IP addresses, one per line"></textarea>
                            <small class="form-text text-muted">Leave empty to allow all IPs</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Add this to your existing JavaScript
$(document).ready(function() {
    // Initialize Select2 for all selects in modals
    $('.modal select').select2({
        theme: 'bootstrap4',
        dropdownParent: $('.modal')
    });

    // Role description update
    $('select[name="role"]').change(function() {
        const description = $(this).find(':selected').data('description');
        $(this).siblings('.role-description').text(description);
    });

    // Profile image preview
    $('input[name="profile_image"]').change(function(e) {
        if (e.target.files && e.target.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $(this).closest('.edit-profile-image').find('img').attr('src', e.target.result);
            }.bind(this);
            reader.readAsDataURL(e.target.files[0]);
        }
    });

    // IP restriction toggle
    $('#ipRestriction').change(function() {
        $('.ip-whitelist').toggle(this.checked);
    });

    // Activity log filters
    $('#activityTypeFilter, #activityDateFilter, #activitySearch').on('change keyup', function() {
        filterActivityLogs();
    });
});

function filterActivityLogs() {
    const type = $('#activityTypeFilter').val();
    const date = $('#activityDateFilter').val();
    const search = $('#activitySearch').val().toLowerCase();

    $('#userLogsTableBody tr').each(function() {
        let show = true;
        
        if (type && !$(this).find('td:eq(1)').text().toLowerCase().includes(type)) {
            show = false;
        }
        
        if (date && !$(this).find('td:eq(0)').text().includes(date)) {
            show = false;
        }
        
        if (search) {
            const text = $(this).text().toLowerCase();
            if (!text.includes(search)) {
                show = false;
            }
        }
        
        $(this).toggle(show);
    });
}

function exportUserLogs() {
    const userId = $('#userLogsModal').data('userId');
    const type = $('#activityTypeFilter').val();
    const date = $('#activityDateFilter').val();
    
    window.location.href = `users_actions.php?action=export_user_logs&user_id=${userId}&type=${type}&date=${date}`;
}
</script> 