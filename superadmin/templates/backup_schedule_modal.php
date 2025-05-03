<?php
// Ensure this file is included, not accessed directly
defined('BASEPATH') or define('BASEPATH', true);
if (!isset($_SESSION)) {
    session_start();
}
?>
<!-- Schedule Backup Modal -->
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

.form-control:focus {
    border-color: #1f9345;
    box-shadow: 0 0 0 0.25rem rgba(31, 147, 69, 0.25);
}

.schedule-type-card {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.schedule-type-card:hover {
    border-color: #1f9345;
    background-color: #f8f9fa;
}

.schedule-type-card.selected {
    border-color: #00572d;
    background-color: #f8f9fa;
}

.schedule-type-card i {
    color: #00572d;
    font-size: 24px;
    margin-bottom: 10px;
}
</style>

<div class="modal fade" id="scheduleBackupModal" tabindex="-1" aria-labelledby="scheduleBackupModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scheduleBackupModalLabel">Schedule Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="scheduleBackupForm">
                    <div class="mb-3">
                        <label class="form-label">Schedule Type</label>
                        <select class="form-select" name="schedule_type" id="scheduleType">
                            <option value="once">One-time</option>
                            <option value="recurring">Recurring</option>
                        </select>
                    </div>

                    <!-- One-time backup fields -->
                    <div id="oneTimeFields">
                        <div class="mb-3">
                            <label class="form-label">Date and Time</label>
                            <input type="datetime-local" class="form-control" name="scheduled_at" min="<?php echo date('Y-m-d\TH:i'); ?>">
                        </div>
                    </div>

                    <!-- Recurring backup fields -->
                    <div id="recurringFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Frequency</label>
                            <select class="form-select" name="frequency">
                                <option value="hourly">Every Hour</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>

                        <div class="mb-3" id="timeField">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" name="schedule_time">
                        </div>

                        <div class="mb-3" id="dayField" style="display: none;">
                            <label class="form-label">Day</label>
                            <select class="form-select" name="schedule_day">
                                <?php for($i = 1; $i <= 31; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="weekdayField" style="display: none;">
                            <label class="form-label">Day of Week</label>
                            <select class="form-select" name="schedule_weekday">
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                                <option value="7">Sunday</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Optional backup description"></textarea>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="notifyOnCompletion" name="notify_on_completion" checked>
                            <label class="form-check-label" for="notifyOnCompletion">Notify me when backup completes</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="scheduleBackup()">Schedule Backup</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#scheduleType').change(function() {
        const type = $(this).val();
        if (type === 'once') {
            $('#oneTimeFields').show();
            $('#recurringFields').hide();
        } else {
            $('#oneTimeFields').hide();
            $('#recurringFields').show();
        }
    });

    $('select[name="frequency"]').change(function() {
        const frequency = $(this).val();
        $('#timeField').show();
        $('#dayField').hide();
        $('#weekdayField').hide();

        switch(frequency) {
            case 'monthly':
                $('#dayField').show();
                break;
            case 'weekly':
                $('#weekdayField').show();
                break;
        }
    });
});

function scheduleBackup() {
    const form = $('#scheduleBackupForm');
    const formData = new FormData(form[0]);
    formData.append('action', 'schedule_backup');

    Swal.fire({
        title: 'Scheduling Backup',
        html: 'Please wait...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: 'backup_actions.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: response.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    $('#scheduleBackupModal').modal('hide');
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: response.error
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'A network error occurred'
            });
        }
    });
}
</script> 