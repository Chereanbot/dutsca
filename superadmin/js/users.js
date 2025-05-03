// Utility: Show toast using SweetAlert2
function showToast(message, type = 'success') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    Toast.fire({
        icon: type,
        title: message
    });
}

// Utility: Show/hide loading using SweetAlert2
function showLoading() {
    Swal.fire({
        title: 'Loading...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        willOpen: () => {
            Swal.showLoading();
        }
    });
}

function hideLoading() {
    Swal.close();
}

// Utility: Format date
function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Utility: Format status badge
function getStatusBadge(status) {
    const statusMap = {
        'active': { color: '#1f9345', text: 'Active' },
        'inactive': { color: '#d32f2f', text: 'Inactive' }
    };
    const statusInfo = statusMap[status] || { color: '#666', text: status };
    return `<span class="bg-[${statusInfo.color}] text-white px-3 py-1 rounded-full text-xs font-semibold">${statusInfo.text}</span>`;
}

// Utility: Handle API errors
function handleApiError(error) {
    console.error('API Error:', error);
    showToast(error.message || 'An error occurred', 'error');
}

// Utility: Validate form data
function validateFormData(formData) {
    const requiredFields = ['name', 'username', 'email', 'department', 'role'];
    const missingFields = requiredFields.filter(field => !formData[field]);
    
    if (missingFields.length > 0) {
        showToast(`Please fill in: ${missingFields.join(', ')}`, 'error');
        return false;
    }
    
    if (formData.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
        showToast('Please enter a valid email address', 'error');
        return false;
    }
    
    return true;
}

// Utility: Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'ETB'
    }).format(amount || 0);
}

// State
let currentPage = 1;
let perPage = 4; // Match your UI
let totalPages = 1;
let lastFilters = {};

function fetchUsers(filters = {}) {
    showLoading();
    lastFilters = filters;
    let params = new URLSearchParams({
        ...filters,
        page: currentPage,
        per_page: perPage
    }).toString();
    fetch('ajax/fetch_users.php?' + params)
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                renderUsersTable(data.users);
                renderPagination(data.page, data.total_pages, data.total);
            } else {
                showToast('Failed to fetch users', 'error');
            }
        })
        .catch(() => {
            hideLoading();
            showToast('Error fetching users', 'error');
        });
}

function renderUsersTable(users) {
    const table = document.querySelector('table.min-w-full');
    const tbody = table ? table.querySelector('tbody') : null;
    if (!tbody) return;
    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-gray-500 py-8">No users found.</td></tr>';
        return;
    }
    tbody.innerHTML = users.map(user => `
        <tr>
            <td class="px-4 py-3"><input type="checkbox"></td>
            <td class="px-4 py-3 flex items-center space-x-3">
                <img src="${user.profile_image || '/assets/images/default-avatar.png'}" class="w-10 h-10 rounded-full object-cover border-2 border-[#1f9345]" alt="Avatar">
                <div>
                    <div class="font-semibold text-gray-900">${user.name}</div>
                    <div class="text-xs text-gray-500">${user.employee_id || ''}</div>
                </div>
            </td>
            <td class="px-4 py-3">${user.department || ''}</td>
            <td class="px-4 py-3">${user.position || user.role || ''}</td>
            <td class="px-4 py-3">${renderStatusBadge(user.status)}</td>
            <td class="px-4 py-3">${formatLastLogin(user.last_login)}</td>
            <td class="px-4 py-3 flex space-x-2">
                <button class="bg-[#e6f4ea] text-[#00572d] p-2 rounded hover:bg-[#d1f0d8] editUserBtn" data-id="${user.id}"><i class="fas fa-pen"></i></button>
                <button class="bg-[#ffeaea] text-[#d32f2f] p-2 rounded hover:bg-[#ffd6d6] deleteUserBtn" data-id="${user.id}"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

function renderStatusBadge(status) {
    if (status === 'active') return '<span class="bg-[#1f9345] text-white px-3 py-1 rounded-full text-xs font-semibold">Active</span>';
    if (status === 'inactive') return '<span class="bg-[#d32f2f] text-white px-3 py-1 rounded-full text-xs font-semibold">Inactive</span>';
    return `<span class="bg-gray-300 text-gray-700 px-3 py-1 rounded-full text-xs font-semibold">${status || ''}</span>`;
}

function formatLastLogin(lastLogin) {
    if (!lastLogin) return '';
    // You can add more formatting logic here
    return lastLogin;
}

function renderPagination(page, totalPagesCount, total) {
    currentPage = page;
    totalPages = totalPagesCount;
    const container = document.querySelector('.flex.justify-between.items-center.mt-4 .flex.space-x-2');
    if (!container) return;
    let html = '';
    html += `<button class="px-3 py-1 rounded border border-gray-300 text-gray-500 bg-white hover:bg-gray-100" data-page="${page - 1}" ${page === 1 ? 'disabled' : ''}>Previous</button>`;
    for (let i = 1; i <= totalPagesCount; i++) {
        html += `<button class="px-3 py-1 rounded border ${i === page ? 'border-[#00572d] text-white bg-[#00572d]' : 'border-gray-300 text-gray-500 bg-white hover:bg-gray-100'}" data-page="${i}">${i}</button>`;
    }
    html += `<button class="px-3 py-1 rounded border border-gray-300 text-gray-500 bg-white hover:bg-gray-100" data-page="${page + 1}" ${page === totalPagesCount ? 'disabled' : ''}>Next</button>`;
    container.innerHTML = html;
    // Add event listeners
    Array.from(container.querySelectorAll('button[data-page]')).forEach(btn => {
        btn.addEventListener('click', function() {
            const p = parseInt(this.getAttribute('data-page'));
            if (p >= 1 && p <= totalPages) {
                currentPage = p;
                fetchUsers(getFiltersFromUI());
            }
        });
    });
}

function getFiltersFromUI() {
    return {
        search: document.getElementById('searchInput').value.trim(),
        department: document.getElementById('filterDepartment').value,
        role: document.getElementById('filterRole').value,
        status: document.getElementById('filterStatus').value
    };
}

function setupEventListeners() {
    document.getElementById('searchInput').addEventListener('input', debounce(function() {
        currentPage = 1;
        fetchUsers(getFiltersFromUI());
    }, 400));
    document.getElementById('applyFiltersBtn').addEventListener('click', function() {
        currentPage = 1;
        fetchUsers(getFiltersFromUI());
    });
    document.getElementById('resetFiltersBtn').addEventListener('click', function() {
        document.getElementById('searchInput').value = '';
        document.getElementById('filterDepartment').value = '';
        document.getElementById('filterRole').value = '';
        document.getElementById('filterStatus').value = '';
        currentPage = 1;
        fetchUsers({});
    });
}

// Debounce utility
function debounce(fn, delay) {
    let timer = null;
    return function(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    fetchUsers({});

    // Modal logic
    const addUserBtn = document.getElementById('addUserBtn');
    const userModal = document.getElementById('userModal');
    const closeUserModal = document.getElementById('closeUserModal');
    const userForm = document.getElementById('userForm');

    addUserBtn.addEventListener('click', function() {
        resetUserForm();
        document.getElementById('userModalTitle').textContent = 'Add User';
        userModal.classList.remove('hidden');
    });
    closeUserModal.addEventListener('click', function() {
        userModal.classList.add('hidden');
    });
    userModal.addEventListener('click', function(e) {
        if (e.target === userModal) userModal.classList.add('hidden');
    });

    userForm.addEventListener('submit', function(e) {
        e.preventDefault();
        showLoading();
        const formData = new FormData(userForm);
        fetch('ajax/add_user.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                showToast('User added successfully', 'success');
                userModal.classList.add('hidden');
                fetchUsers(getFiltersFromUI());
            } else {
                showToast(data.error || 'Failed to add user', 'error');
            }
        })
        .catch(() => {
            hideLoading();
            showToast('Error adding user', 'error');
        });
    });
});

function resetUserForm() {
    userForm.reset();
    document.getElementById('userIdInput').value = '';
    document.getElementById('userProfilePreview').src = '/assets/images/default-avatar.png';
    // Reset all readonly fields
    [
        'userLastLoginInput', 'userLoginAttemptsInput', 'userEmailVerifiedInput',
        'userCreatedAtInput', 'userUpdatedAtInput', 'userApprovedAtInput', 'userApprovedByInput'
    ].forEach(id => {
        if (document.getElementById(id)) document.getElementById(id).value = '';
    });
} 