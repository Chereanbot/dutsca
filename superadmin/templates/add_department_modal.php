<!-- Add Department Modal -->
<style>
.modal-header {
    background-color: #00572d;
    color: white;
}

.modal-header .btn-close {
    color: white;
    filter: brightness(0) invert(1);
}

.form-label {
    color: #333333;
    font-weight: 500;
}

.btn-primary {
    background-color: #00572d;
    border-color: #00572d;
}

.btn-primary:hover {
    background-color: #1f9345;
    border-color: #1f9345;
}

.btn-secondary {
    background-color: #6c757d;
    border-color: #6c757d;
}

.btn-secondary:hover {
    background-color: #5a6268;
    border-color: #545b62;
}

.form-control:focus {
    border-color: #1f9345;
    box-shadow: 0 0 0 0.25rem rgba(31, 147, 69, 0.25);
}
</style>

<div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDepartmentModalLabel">Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addDepartmentForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_department">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="contact_email" class="form-label">Contact Email</label>
                        <input type="email" class="form-control" id="contact_email" name="contact_email">
                    </div>

                    <div class="mb-3">
                        <label for="contact_phone" class="form-label">Contact Phone</label>
                        <input type="tel" class="form-control" id="contact_phone" name="contact_phone">
                    </div>

                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#addDepartmentForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'department_actions.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        // Close modal
                        $('#addDepartmentModal').modal('hide');
                        // Refresh the page to show new department
                        location.reload();
                    });
                } else {
                    // Show error message
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: response.error || 'An error occurred while adding the department.',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'A network error occurred. Please try again.',
                    confirmButtonText: 'OK'
                });
            }
        });
    });

    // Clear form when modal is closed
    $('#addDepartmentModal').on('hidden.bs.modal', function() {
        $('#addDepartmentForm')[0].reset();
    });
});
</script> 