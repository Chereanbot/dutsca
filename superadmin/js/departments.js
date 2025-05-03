// Show toast and loading (replace with your implementations)
function showToast(message, type = 'success') { alert(message); }
function showLoading() {}
function hideLoading() {}

function fetchDepartments(query = '') {
    showLoading();
    fetch('ajax/departments.php?action=list&query=' + encodeURIComponent(query))
        .then(res => res.json())
        .then(data => {
            hideLoading();
            if (data.success) renderDepartmentsTable(data.departments);
            else showToast(data.error || 'Failed to fetch departments', 'error');
        })
        .catch(() => { hideLoading(); showToast('Error fetching departments', 'error'); });
}

function renderDepartmentsTable(departments) {
    const container = document.getElementById('departmentsTableContainer');
    if (!departments.length) {
        container.innerHTML = '<div class="text-center text-gray-500 py-8">No departments found.</div>';
        return;
    }
    let html = `<div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="bg-[#e6f4ea] text-[#00572d]"><th class="py-2 px-4">#</th><th class="py-2 px-4">Name</th><th class="py-2 px-4">Description</th><th class="py-2 px-4">Head (User ID)</th><th class="py-2 px-4">Actions</th></tr></thead><tbody>`;
    departments.forEach((d, i) => {
        html += `<tr class="border-b"><td class="py-2 px-4">${i+1}</td><td class="py-2 px-4">${d.name}</td><td class="py-2 px-4">${d.description||''}</td><td class="py-2 px-4">${d.head_user_id||''}</td><td class="py-2 px-4"><button class="editDepartmentBtn text-[#00572d] hover:underline mr-2" data-id="${d.id}">Edit</button><button class="deleteDepartmentBtn text-red-500 hover:underline" data-id="${d.id}">Delete</button></td></tr>`;
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function openDepartmentModal(dept = null) {
    document.getElementById('departmentModal').classList.remove('hidden');
    document.getElementById('departmentModalTitle').textContent = dept ? 'Edit Department' : 'Add Department';
    document.getElementById('departmentIdInput').value = dept ? dept.id : '';
    document.getElementById('departmentNameInput').value = dept ? dept.name : '';
    document.getElementById('departmentDescriptionInput').value = dept ? dept.description : '';
    document.getElementById('departmentHeadInput').value = dept ? dept.head_user_id : '';
}

function closeDepartmentModal() {
    document.getElementById('departmentModal').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', function() {
    fetchDepartments();
    document.getElementById('addDepartmentBtn').onclick = () => openDepartmentModal();
    document.getElementById('closeDepartmentModal').onclick = closeDepartmentModal;
    document.getElementById('departmentForm').onsubmit = function(e) {
        e.preventDefault();
        const id = document.getElementById('departmentIdInput').value;
        const name = document.getElementById('departmentNameInput').value;
        const description = document.getElementById('departmentDescriptionInput').value;
        const head_user_id = document.getElementById('departmentHeadInput').value;
        const formData = new FormData();
        formData.append('action', id ? 'edit' : 'add');
        if (id) formData.append('id', id);
        formData.append('name', name);
        formData.append('description', description);
        formData.append('head_user_id', head_user_id);
        showLoading();
        fetch('ajax/departments.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast(data.message || 'Saved successfully');
                    closeDepartmentModal();
                    fetchDepartments();
                } else showToast(data.error || 'Failed to save', 'error');
            })
            .catch(() => { hideLoading(); showToast('Error saving department', 'error'); });
    };
    document.getElementById('departmentsTableContainer').onclick = function(e) {
        if (e.target.classList.contains('editDepartmentBtn')) {
            const id = e.target.dataset.id;
            showLoading();
            fetch('ajax/departments.php?action=get&id=' + id)
                .then(res => res.json())
                .then(data => {
                    hideLoading();
                    if (data.success) openDepartmentModal(data.department);
                    else showToast(data.error || 'Not found', 'error');
                })
                .catch(() => { hideLoading(); showToast('Error loading department', 'error'); });
        } else if (e.target.classList.contains('deleteDepartmentBtn')) {
            if (!confirm('Delete this department?')) return;
            const id = e.target.dataset.id;
            showLoading();
            fetch('ajax/departments.php', { method: 'POST', body: new URLSearchParams({ action: 'delete', id }) })
                .then(res => res.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showToast(data.message || 'Deleted successfully');
                        fetchDepartments();
                    } else showToast(data.error || 'Failed to delete', 'error');
                })
                .catch(() => { hideLoading(); showToast('Error deleting department', 'error'); });
        }
    };
    document.getElementById('applyDepartmentSearchBtn').onclick = function() {
        fetchDepartments(document.getElementById('searchDepartmentInput').value);
    };
    document.getElementById('resetDepartmentSearchBtn').onclick = function() {
        document.getElementById('searchDepartmentInput').value = '';
        fetchDepartments();
    };
}); 