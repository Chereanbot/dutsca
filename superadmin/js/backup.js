document.addEventListener('DOMContentLoaded', function() {
    const tableBody = document.querySelector('#backups-table tbody');
    const createBtn = document.getElementById('create-backup-btn');
    const modal = new bootstrap.Modal(document.getElementById('backupModal'));
    let currentAction = null;
    let currentBackupId = null;

    // Backup Modal Functions
    function openBackupModal(action, backupId = null) {
        const modal = new bootstrap.Modal(document.getElementById('backupModal'));
        const modalBody = document.getElementById('backupModalBody');
        const modalConfirm = document.getElementById('backupModalConfirm');
        
        // Reset modal
        modalBody.innerHTML = '';
        modalConfirm.onclick = null;
        
        // Set modal title
        document.getElementById('backupModalLabel').textContent = action.charAt(0).toUpperCase() + action.slice(1);
        
        // Add progress bar
        const progressBar = document.createElement('div');
        progressBar.className = 'progress mb-3';
        progressBar.innerHTML = `
            <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
        `;
        modalBody.appendChild(progressBar);
        
        // Add status text
        const statusText = document.createElement('div');
        statusText.className = 'text-center mb-3';
        statusText.textContent = 'Starting...';
        modalBody.appendChild(statusText);
        
        // Add logs container
        const logsContainer = document.createElement('div');
        logsContainer.className = 'logs-container';
        modalBody.appendChild(logsContainer);
        
        // Set up confirm button
        modalConfirm.onclick = () => {
            modal.hide();
        };
        
        // Show modal
        modal.show();
        
        return {
            progressBar: progressBar.querySelector('.progress-bar'),
            statusText,
            logsContainer,
            modal
        };
    }

    function showToast(message, type = 'info') {
        // Simple toast (replace with your toast system if needed)
        alert(message);
    }

    function showLoading(show) {
        // Simple loading (replace with your loading system if needed)
        document.body.style.cursor = show ? 'wait' : '';
    }

    function fetchBackups() {
        showLoading(true);
        fetch('ajax/backup.php?action=list')
            .then(res => res.json())
            .then(data => {
                tableBody.innerHTML = '';
                if (data.success && data.backups.length) {
                    data.backups.forEach((b, i) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${i+1}</td>
                            <td>${b.backup_type}</td>
                            <td>${b.file_path}</td>
                            <td><span class="badge bg-${b.status === 'completed' ? 'success' : (b.status === 'pending' ? 'warning' : 'danger')}">${b.status}</span></td>
                            <td>${b.size ? (b.size/1024/1024).toFixed(2) + ' MB' : '-'}</td>
                            <td>${b.created_at}</td>
                            <td>
                                <button class="btn btn-sm btn-primary me-1" data-action="download" data-id="${b.id}"><i class="fas fa-download"></i></button>
                                <button class="btn btn-sm btn-success me-1" data-action="restore" data-id="${b.id}"><i class="fas fa-undo"></i></button>
                                <button class="btn btn-sm btn-danger" data-action="delete" data-id="${b.id}"><i class="fas fa-trash"></i></button>
                            </td>
                        `;
                        tableBody.appendChild(row);
                    });
                } else {
                    tableBody.innerHTML = '<tr><td colspan="7" class="text-center">No backups found.</td></tr>';
                }
                showLoading(false);
            })
            .catch(() => {
                showToast('Failed to load backups', 'error');
                showLoading(false);
            });
    }

    tableBody.addEventListener('click', function(e) {
        const btn = e.target.closest('button');
        if (!btn) return;
        const action = btn.getAttribute('data-action');
        const id = btn.getAttribute('data-id');
        currentAction = action;
        currentBackupId = id;
        let body = '';
        if (action === 'download') {
            window.location = `ajax/backup.php?action=download&id=${id}`;
            return;
        } else if (action === 'restore') {
            body = 'Are you sure you want to restore this backup? This will overwrite current data.';
        } else if (action === 'delete') {
            body = 'Are you sure you want to delete this backup? This cannot be undone.';
        }
        document.getElementById('backupModalBody').textContent = body;
        document.getElementById('backupModalLabel').textContent = action.charAt(0).toUpperCase() + action.slice(1) + ' Backup';
        modal.show();
    });

    document.getElementById('backupModalConfirm').addEventListener('click', function() {
        if (!currentAction || !currentBackupId) return;
        showLoading(true);
        fetch('ajax/backup.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: currentAction, id: currentBackupId })
        })
        .then(res => res.json())
        .then(data => {
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) fetchBackups();
            modal.hide();
            showLoading(false);
        })
        .catch(() => {
            showToast('Action failed', 'error');
            showLoading(false);
        });
    });

    createBtn.addEventListener('click', function() {
        showLoading(true);
        fetch('ajax/backup.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'create' })
        })
        .then(res => res.json())
        .then(data => {
            showToast(data.message, data.success ? 'success' : 'error');
            if (data.success) fetchBackups();
            showLoading(false);
        })
        .catch(() => {
            showToast('Backup creation failed', 'error');
            showLoading(false);
        });
    });

    fetchBackups();
}); 