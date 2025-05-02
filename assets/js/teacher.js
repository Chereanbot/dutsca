// DUTSCA Teacher Portal JavaScript

// Toast Notification System
const Toast = {
    container: null,
    
    init() {
        if (!this.container) {
            this.container = document.getElementById('toastContainer');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toastContainer';
                this.container.className = 'fixed top-4 right-4 z-50 space-y-4';
                document.body.appendChild(this.container);
            }
        }
    },

    show(type, message, duration = 5000) {
        this.init();
        
        const toast = document.createElement('div');
        let icon, borderColor;
        
        switch(type) {
            case 'success':
                icon = '✅';
                borderColor = 'border-[#1f9345]';
                break;
            case 'error':
                icon = '❌';
                borderColor = 'border-[#d93025]';
                break;
            case 'info':
                icon = '⚠️';
                borderColor = 'border-[#f3c300]';
                break;
            default:
                icon = 'ℹ️';
                borderColor = 'border-[#1f9345]';
        }
        
        toast.className = `toast bg-white border-l-4 ${borderColor} text-[#00572d] p-4 rounded shadow-lg flex items-start gap-2`;
        toast.innerHTML = `
            <span>${icon}</span>
            <div class="flex-1">
                <p class="font-bold capitalize">${type}</p>
                <p>${message}</p>
            </div>
            <button class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Close button functionality
        toast.querySelector('button').addEventListener('click', () => {
            toast.remove();
        });
        
        this.container.appendChild(toast);
        
        // Auto remove after duration
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, duration);
    }
};

// Loading Overlay System
const LoadingOverlay = {
    overlay: null,
    
    init() {
        if (!this.overlay) {
            this.overlay = document.createElement('div');
            this.overlay.className = 'fixed inset-0 z-50 loading-overlay flex flex-col items-center justify-center hidden';
            this.overlay.innerHTML = `
                <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-[#f3c300] border-solid"></div>
                <p class="mt-4 text-[#00572d] font-semibold text-lg">Securely processing...</p>
            `;
            document.body.appendChild(this.overlay);
        }
    },
    
    show(message = 'Securely processing...') {
        this.init();
        this.overlay.querySelector('p').textContent = message;
        this.overlay.classList.remove('hidden');
    },
    
    hide() {
        if (this.overlay) {
            this.overlay.classList.add('hidden');
        }
    }
};

// Form Validation
const FormValidator = {
    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    validatePassword(password) {
        return password.length >= 8;
    },
    
    validateAmount(amount) {
        return !isNaN(amount) && parseFloat(amount) > 0;
    },
    
    showError(inputElement, message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'text-red-500 text-sm mt-1';
        errorDiv.textContent = message;
        
        // Remove any existing error message
        const existingError = inputElement.parentElement.querySelector('.text-red-500');
        if (existingError) {
            existingError.remove();
        }
        
        inputElement.parentElement.appendChild(errorDiv);
        inputElement.classList.add('border-red-500');
    },
    
    clearError(inputElement) {
        const errorDiv = inputElement.parentElement.querySelector('.text-red-500');
        if (errorDiv) {
            errorDiv.remove();
        }
        inputElement.classList.remove('border-red-500');
    }
};

// Number Formatting
const NumberFormatter = {
    formatCurrency(amount) {
        return new Intl.NumberFormat('en-PH', {
            style: 'currency',
            currency: 'PHP'
        }).format(amount);
    },
    
    formatNumber(number) {
        return new Intl.NumberFormat('en').format(number);
    },
    
    formatPercentage(number) {
        return new Intl.NumberFormat('en', {
            style: 'percent',
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(number / 100);
    }
};

// Date Formatting
const DateFormatter = {
    format(date, format = 'full') {
        const d = new Date(date);
        
        switch(format) {
            case 'full':
                return d.toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            case 'short':
                return d.toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            case 'time':
                return d.toLocaleTimeString('en-PH', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            default:
                return d.toLocaleString('en-PH');
        }
    },
    
    timeAgo(date) {
        const seconds = Math.floor((new Date() - new Date(date)) / 1000);
        
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + ' years ago';
        
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + ' months ago';
        
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + ' days ago';
        
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + ' hours ago';
        
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + ' minutes ago';
        
        return Math.floor(seconds) + ' seconds ago';
    }
};

// Mobile Navigation
const MobileNav = {
    init() {
        const menuButton = document.querySelector('[data-drawer-toggle="sidebar"]');
        const sidebar = document.getElementById('teacherSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (menuButton && sidebar) {
            menuButton.addEventListener('click', () => this.toggleSidebar(sidebar, overlay));
            
            if (overlay) {
                overlay.addEventListener('click', () => this.toggleSidebar(sidebar, overlay));
            }
            
            // Close sidebar on window resize if in mobile view
            window.addEventListener('resize', () => {
                if (window.innerWidth >= 768 && !sidebar.classList.contains('-translate-x-full')) {
                    this.toggleSidebar(sidebar, overlay);
                }
            });
        }
    },
    
    toggleSidebar(sidebar, overlay) {
        sidebar.classList.toggle('-translate-x-full');
        if (overlay) {
            overlay.classList.toggle('hidden');
        }
    }
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize mobile navigation
    MobileNav.init();
    
    // Initialize toast container
    Toast.init();
    
    // Initialize loading overlay
    LoadingOverlay.init();
}); 