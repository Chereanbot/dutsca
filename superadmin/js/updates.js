// Update variables
let currentUpdate = null;
let updateProgress = 0;

// DOM Elements
const checkUpdatesBtn = document.getElementById('checkUpdatesBtn');
const applyUpdatesBtn = document.getElementById('applyUpdatesBtn');
const updateModal = new bootstrap.Modal(document.getElementById('updateModal'));
const progressBar = document.querySelector('.progress-bar');
const progressStatus = document.getElementById('progressStatus');
const updateLog = document.querySelector('.log-entries');
const confirmUpdateBtn = document.getElementById('confirmUpdate');
const lastChecked = document.getElementById('lastChecked');
const updateStatus = document.getElementById('updateStatus');
const updateMessage = document.getElementById('updateMessage');

// Event Listeners
checkUpdatesBtn.addEventListener('click', checkForUpdates);
confirmUpdateBtn.addEventListener('click', startUpdateProcess);

// Functions
async function checkForUpdates() {
    try {
        const response = await fetch('/superadmin/updates.php?action=check_updates', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (data.updates.length > 0) {
                updateStatus.textContent = 'Updates Available';
                updateStatus.style.color = '#dc2626';
                updateMessage.textContent = `${data.updates.length} updates available`;
                applyUpdatesBtn.disabled = false;
                
                // Update available updates table
                const updatesTable = document.getElementById('updatesTable');
                const tbody = updatesTable.querySelector('tbody');
                tbody.innerHTML = '';
                
                data.updates.forEach(update => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${update.version}</td>
                        <td>${update.type}</td>
                        <td>${update.description}</td>
                        <td>${update.size}</td>
                        <td>
                            <span class="badge bg-${update.status === 'security' ? 'danger' : 'primary'}">
                                ${update.status === 'security' ? 'Security' : 'Regular'}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="selectUpdate('${update.version}')">
                                Install
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            } else {
                updateStatus.textContent = 'Up to date';
                updateStatus.style.color = '#10b981';
                updateMessage.textContent = 'Your system is currently up to date';
                applyUpdatesBtn.disabled = true;
                
                // Show no updates message
                const tbody = document.getElementById('updatesTable').querySelector('tbody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-4">No updates available</td>
                    </tr>
                `;
            }
            
            lastChecked.textContent = new Date().toLocaleString();
            showToast('Update check completed successfully!', 'success');
        } else {
            showToast('Failed to check for updates', 'error');
        }
    } catch (error) {
        console.error('Error checking for updates:', error);
        showToast('Error checking for updates', 'error');
    }
}

function selectUpdate(version) {
    currentUpdate = version;
    updateModal.show();
}

async function startUpdateProcess() {
    if (!currentUpdate) return;

    try {
        // Show progress UI
        document.querySelector('.update-confirmation').classList.add('d-none');
        document.querySelector('.update-progress').classList.remove('d-none');
        progressBar.style.width = '0%';
        progressStatus.textContent = 'Starting update process...';
        updateLog.innerHTML = '';

        // Start update
        const response = await fetch('/superadmin/updates.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=install_update&version=${currentUpdate}`
        });

        const data = await response.json();

        if (data.success) {
            // Update progress bar and log messages
            data.logs.forEach(log => {
                const logEntry = document.createElement('div');
                logEntry.className = 'text-muted small mb-1';
                logEntry.textContent = log;
                updateLog.appendChild(logEntry);
            });

            progressBar.style.width = '100%';
            progressStatus.textContent = 'Update completed successfully';
            
            showToast('Update installed successfully!', 'success');
            
            // Refresh page after a delay
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            showToast('Failed to install update', 'error');
        }
    } catch (error) {
        console.error('Error installing update:', error);
        showToast('Error installing update', 'error');
    }
}

async function rollbackUpdate(updateId) {
    if (!confirm('Are you sure you want to rollback this update? This action cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch('/superadmin/updates.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=rollback_update&update_id=${updateId}`
        });

        const data = await response.json();

        if (data.success) {
            showToast('Update rolled back successfully!', 'success');
            window.location.reload();
        } else {
            showToast('Failed to rollback update', 'error');
        }
    } catch (error) {
        console.error('Error rolling back update:', error);
        showToast('Error rolling back update', 'error');
    }
}

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast ${type === 'success' ? 'bg-success' : 'bg-danger'} text-white position-fixed bottom-0 end-0 m-3 p-3 rounded`;
    toast.style.zIndex = '1050';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white ms-auto me-2" data-bs-dismiss="toast"></button>
        </div>
    `;

    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    setTimeout(() => {
        bsToast.hide();
        toast.remove();
    }, 3000);
}

// Auto-check for updates every 5 minutes
document.addEventListener('DOMContentLoaded', () => {
    checkForUpdates();
    setInterval(checkForUpdates, 5 * 60 * 1000);
});
