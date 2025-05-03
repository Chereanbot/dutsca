<?php if (!defined('FOOTER_INCLUDED')) { define('FOOTER_INCLUDED', true); ?>
<style>
.footer {
    margin-left: var(--sidebar-width);
    padding: 1rem 2rem;
    background: white;
    border-top: 1px solid #e9ecef;
    font-size: 0.875rem;
    color: #6c757d;
}

.footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.footer-links {
    display: flex;
    gap: 1.5rem;
}

.footer-links a {
    color: #6c757d;
    text-decoration: none;
    transition: color 0.3s ease;
}

.footer-links a:hover {
    color: var(--primary-color);
}

.footer-version {
    font-size: 0.75rem;
    opacity: 0.8;
    margin-left: 1rem;
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    bottom: 1rem;
    right: 1rem;
    z-index: 1060;
}

.toast {
    background: white;
    border: none;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    border-radius: 0.5rem;
    min-width: 300px;
}

.toast.bg-success,
.toast.bg-danger,
.toast.bg-warning,
.toast.bg-info {
    color: white;
}

.toast.bg-warning {
    color: #000;
}

.toast .toast-header {
    background: transparent;
    border: none;
    padding: 0.75rem 1rem;
}

.toast .toast-body {
    padding: 0.75rem 1rem;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}
</style>

<footer class="footer">
    <div class="footer-content">
        <div class="d-flex align-items-center">
            <span>&copy; <?php echo date('Y'); ?> DUTSCA. All rights reserved.</span>
            <span class="footer-version">Version 1.0.0</span>
        </div>
        <div class="footer-links">
            <a href="/dutsca/privacy-policy.php">Privacy Policy</a>
            <a href="/dutsca/terms-of-service.php">Terms of Service</a>
            <a href="/dutsca/contact.php">Contact</a>
        </div>
    </div>
</footer>

<!-- Toast Notifications -->
<div class="toast-container">
    <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="fas fa-info-circle me-2"></i>
            <strong class="me-auto" id="toastTitle">Notification</strong>
            <small id="toastTime">Just now</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage"></div>
    </div>
</div>

<script>
function showToast(title, message, type = 'info') {
    const toast = document.getElementById('liveToast');
    const toastTitle = document.getElementById('toastTitle');
    const toastMessage = document.getElementById('toastMessage');
    const toastTime = document.getElementById('toastTime');
    
    toastTitle.textContent = title;
    toastMessage.textContent = message;
    toastTime.textContent = 'Just now';
    
    toast.className = 'toast';
    switch(type) {
        case 'success':
            toast.classList.add('bg-success', 'text-white');
            break;
        case 'error':
            toast.classList.add('bg-danger', 'text-white');
            break;
        case 'warning':
            toast.classList.add('bg-warning');
            break;
        default:
            toast.classList.add('bg-info', 'text-white');
    }
    
    const bsToast = new bootstrap.Toast(toast, {
        animation: true,
        autohide: true,
        delay: 5000
    });
    bsToast.show();
}
</script>
<?php } ?> 