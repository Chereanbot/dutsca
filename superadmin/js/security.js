// Security page JavaScript

// DOM Elements
const runScanBtn = document.getElementById('runScanBtn');
const alertDetailsModal = new bootstrap.Modal(document.getElementById('alertDetailsModal'));
const scanDetailsModal = new bootstrap.Modal(document.getElementById('scanDetailsModal'));

// Event Listeners
runScanBtn.addEventListener('click', runSecurityScan);

// Security Functions
async function runSecurityScan() {
    try {
        const response = await fetch('/superadmin/security.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=run_scan'
        });

        const data = await response.json();

        if (data.success) {
            showToast('Security scan completed successfully!', 'success');
            // Update scan history
            updateScanHistory();
        } else {
            showToast('Failed to run security scan', 'error');
        }
    } catch (error) {
        console.error('Error running security scan:', error);
        showToast('Error running security scan', 'error');
    }
}

async function updatePasswordPolicy() {
    const form = document.getElementById('passwordPolicyForm');
    const formData = new FormData(form);

    try {
        const response = await fetch('/superadmin/security.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(formData)
        });

        const data = await response.json();

        if (data.success) {
            showToast('Password policy updated successfully!', 'success');
            form.reset();
        } else {
            showToast('Failed to update password policy', 'error');
        }
    } catch (error) {
        console.error('Error updating password policy:', error);
        showToast('Error updating password policy', 'error');
    }
    return false;
}

function viewAlertDetails(alertId) {
    // Fetch alert details
    fetch(`/superadmin/security.php?action=get_alert_details&alert_id=${alertId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('alertDetailsContent').innerHTML = `
                    <div class="alert alert-${data.alert.severity}">
                        <h5 class="alert-heading">${data.alert.type}</h5>
                        <p>${data.alert.description}</p>
                        <p class="mb-0">Detected: ${new Date(data.alert.created_at).toLocaleString()}</p>
                    </div>
                `;
                alertDetailsModal.show();
            }
        })
        .catch(error => {
            console.error('Error fetching alert details:', error);
            showToast('Error fetching alert details', 'error');
        });
}

function resolveAlert(alertId) {
    if (!confirm('Are you sure you want to resolve this alert?')) {
        return;
    }

    fetch(`/superadmin/security.php?action=resolve_alert&alert_id=${alertId}`, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Alert resolved successfully!', 'success');
            // Refresh alerts list
            updateAlertsList();
        } else {
            showToast('Failed to resolve alert', 'error');
        }
    })
    .catch(error => {
        console.error('Error resolving alert:', error);
        showToast('Error resolving alert', 'error');
    });
}

function viewScanDetails(scanId) {
    // Fetch scan details
    fetch(`/superadmin/security.php?action=get_scan_details&scan_id=${scanId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const results = JSON.parse(data.scan.results);
                let content = `
                    <div class="mb-4">
                        <h5>Scan Details</h5>
                        <p><strong>Scan Type:</strong> ${data.scan.scan_type}</p>
                        <p><strong>Status:</strong> ${data.scan.status}</p>
                        <p><strong>Scan Time:</strong> ${new Date(data.scan.scan_time).toLocaleString()}</p>
                    </div>
                    <div class="mb-4">
                        <h5>Scan Results</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Check</th>
                                        <th>Status</th>
                                        <th>Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;

                Object.entries(results).forEach(([check, result]) => {
                    content += `
                        <tr>
                            <td>${check}</td>
                            <td>
                                <span class="badge bg-${result.status === 'ok' ? 'success' : (result.status === 'warning' ? 'warning' : 'danger')}">
                                    ${result.status}
                                </span>
                            </td>
                            <td>${result.message}</td>
                        </tr>
                    `;
                });

                content += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;

                document.getElementById('scanDetailsContent').innerHTML = content;
                scanDetailsModal.show();
            }
        })
        .catch(error => {
            console.error('Error fetching scan details:', error);
            showToast('Error fetching scan details', 'error');
        });
}

// Helper Functions
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

// Auto-refresh functions
function updateAlertsList() {
    fetch('/superadmin/security.php?action=get_alerts')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update alerts table
                const tbody = document.querySelector('#alertsTable tbody');
                tbody.innerHTML = '';
                
                data.alerts.forEach(alert => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${alert.type}</td>
                        <td>
                            <span class="badge bg-${alert.severity === 'critical' ? 'danger' : (alert.severity === 'warning' ? 'warning' : 'info')}">
                                ${alert.severity}
                            </span>
                        </td>
                        <td>${alert.description}</td>
                        <td>${new Date(alert.created_at).toLocaleString()}</td>
                        <td>
                            <span class="badge bg-${alert.status === 'active' ? 'primary' : 'success'}">
                                ${alert.status}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewAlertDetails(${alert.id})">
                                <i class="fas fa-info-circle"></i>
                            </button>
                            ${alert.status === 'active' ? `
                                <button class="btn btn-sm btn-success" onclick="resolveAlert(${alert.id})">
                                    <i class="fas fa-check"></i>
                                </button>
                            ` : ''}
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
        });
}

function updateScanHistory() {
    fetch('/superadmin/security.php?action=get_scan_history')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update scan history table
                const tbody = document.querySelector('#scanHistoryTable tbody');
                tbody.innerHTML = '';
                
                data.scans.forEach(scan => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${new Date(scan.scan_time).toLocaleString()}</td>
                        <td>${scan.scan_type}</td>
                        <td>
                            <span class="badge bg-${scan.status === 'completed' ? 'success' : 'warning'}">
                                ${scan.status}
                            </span>
                        </td>
                        <td>
                            ${Object.values(JSON.parse(scan.results))
                                .filter(r => r.status !== 'ok')
                                .length}
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewScanDetails(${scan.id})">
                                <i class="fas fa-info-circle"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
        });
}

// Auto-refresh data every 5 minutes
document.addEventListener('DOMContentLoaded', () => {
    setInterval(updateAlertsList, 5 * 60 * 1000);
    setInterval(updateScanHistory, 5 * 60 * 1000);
});
