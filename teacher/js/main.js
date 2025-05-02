// DOM Elements
const sidebar = document.getElementById('teacherSidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const menuButton = document.querySelector('[data-drawer-toggle="sidebar"]');
const loadingOverlay = document.getElementById('loadingOverlay');
const toastContainer = document.getElementById('toastContainer');

// Sidebar Toggle
function toggleSidebar() {
    sidebar.classList.toggle('-translate-x-full');
    sidebarOverlay.classList.toggle('hidden');
    document.body.classList.toggle('overflow-hidden');
}

// Event Listeners
menuButton?.addEventListener('click', toggleSidebar);
sidebarOverlay?.addEventListener('click', toggleSidebar);

// Close sidebar on window resize if mobile view
window.addEventListener('resize', () => {
    if (window.innerWidth >= 768 && !sidebar.classList.contains('-translate-x-full')) {
        toggleSidebar();
    }
});

// Toast Notification System
function showToast(type, message, duration = 5000) {
    const toast = document.createElement('div');
    let bgColor, borderColor, icon;
    
    switch(type) {
        case 'success':
            bgColor = 'bg-white';
            borderColor = 'border-[#1f9345]';
            icon = '✅';
            break;
        case 'error':
            bgColor = 'bg-white';
            borderColor = 'border-red-600';
            icon = '❌';
            break;
        case 'warning':
            bgColor = 'bg-white';
            borderColor = 'border-[#f3c300]';
            icon = '⚠️';
            break;
        default:
            bgColor = 'bg-white';
            borderColor = 'border-[#00572d]';
            icon = 'ℹ️';
    }
    
    toast.className = `toast ${bgColor} border-l-4 ${borderColor} p-4 rounded-lg shadow-lg flex items-start gap-3`;
    toast.innerHTML = `
        <span>${icon}</span>
        <div class="flex-1">
            <p class="font-semibold capitalize">${type}</p>
            <p class="text-gray-600">${message}</p>
        </div>
        <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    toastContainer.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Loading Overlay
function showLoading() {
    loadingOverlay.classList.remove('hidden');
}

function hideLoading() {
    loadingOverlay.classList.add('hidden');
}

// Clock In/Out System
function handleClockInOut() {
    const clockButton = document.querySelector('.clock-button');
    if (!clockButton) return;

    clockButton.addEventListener('click', async () => {
        try {
            showLoading();
            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            const isClockingIn = clockButton.textContent.includes('Clock In');
            const message = isClockingIn ? 'Clocked in successfully!' : 'Clocked out successfully!';
            
            showToast('success', message);
            
            // Toggle button text
            const icon = clockButton.querySelector('i');
            const span = clockButton.querySelector('span');
            
            if (isClockingIn) {
                icon.classList.replace('fa-sign-in-alt', 'fa-sign-out-alt');
                span.textContent = 'Clock Out';
            } else {
                icon.classList.replace('fa-sign-out-alt', 'fa-sign-in-alt');
                span.textContent = 'Clock In';
            }
        } catch (error) {
            showToast('error', 'Failed to process your request. Please try again.');
        } finally {
            hideLoading();
        }
    });
}

// Quick Actions Menu
function initializeQuickActions() {
    const quickActionsBtn = document.querySelector('.quick-actions-btn');
    if (!quickActionsBtn) return;

    quickActionsBtn.addEventListener('click', () => {
        // Implementation for quick actions menu
        showToast('info', 'Quick actions menu coming soon!');
    });
}

// Status Cards Animation
function initializeStatusCards() {
    const cards = document.querySelectorAll('.status-card');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fadeIn');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    cards.forEach(card => observer.observe(card));
}

// Initialize all components
document.addEventListener('DOMContentLoaded', () => {
    handleClockInOut();
    initializeQuickActions();
    initializeStatusCards();
});

// Export functions for use in other files
window.showToast = showToast;
window.showLoading = showLoading;
window.hideLoading = hideLoading; 