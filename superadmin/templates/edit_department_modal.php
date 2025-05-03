<div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Edit Department: <?php echo htmlspecialchars($department['name']); ?></h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <form id="editDepartmentForm">
            <div class="modal-body">
                <input type="hidden" name="action" value="update_department">
                <input type="hidden" name="department_id" value="<?php echo $department['id']; ?>">
                
                <div class="form-group">
                    <label>Department Name</label>
                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($department['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($department['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Department Head</label>
                    <select class="form-control" name="head_id">
                        <option value="">Select Department Head</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo ($user['id'] == $department['head_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?> (<?php echo $user['email']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Contact Email</label>
                    <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($department['contact_email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Contact Phone</label>
                    <input type="text" class="form-control" name="contact_phone" value="<?php echo htmlspecialchars($department['contact_phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($department['location'] ?? ''); ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#editDepartmentForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        $.ajax({
            url: 'department_actions.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                    $('#editDepartmentModal').modal('hide');
                    location.reload();
                } else {
                    showToast(response.error, 'error');
                }
            }
        });
    });
});
</script> 