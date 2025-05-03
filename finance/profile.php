<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'finance') {
    header('Location: /dutsca/index.php');
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../include/Logger.php';
require_once __DIR__ . '/include/sidebar.php';
require_once __DIR__ . '/include/header.php';
$db = getDB();
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);
if (!$user) {
    echo '<div class="alert alert-danger">User not found.</div>';
    exit;
}

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = [];
    $params = [];
    $editable = [
        'name', 'contact_number', 'department', 'position', 'profile_image',
        'bank_name', 'bank_branch', 'account_number',
    ];
    foreach ($editable as $field) {
        if (isset($_POST[$field]) && $_POST[$field] !== $user[$field]) {
            $fields[] = "$field = ?";
            $params[] = trim($_POST[$field]);
        }
    }
    // Handle profile image upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','gif'];
        $profileDir = __DIR__ . '/../assets/images/profile/';
        if (!is_dir($profileDir)) {
            if (!mkdir($profileDir, 0777, true)) {
                $error = 'Failed to create profile image directory.';
            }
        }
        if (empty($error) && !is_writable($profileDir)) {
            $error = 'Profile image directory is not writable.';
        }
        if (in_array($ext, $allowed) && empty($error)) {
            $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
            $dest = $profileDir . $filename;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dest)) {
                $fields[] = "profile_image = ?";
                $params[] = $filename;
            } else {
                $error = 'Failed to upload profile image.';
            }
        } elseif (empty($error)) {
            $error = 'Invalid image format.';
        }
    }
    if ($fields && !$error) {
        $params[] = $user_id;
        $sql = "UPDATE users SET ".implode(", ", $fields).", updated_at = NOW() WHERE id = ?";
        try {
            $db->executeQuery($sql, $params);
            $success = 'Profile updated successfully!';
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$user_id]);
        } catch (Exception $e) {
            $error = 'Error updating profile: ' . $e->getMessage();
        }
    } elseif (!$fields && !$error) {
        $success = 'No changes detected.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f4f4; }
        .profile-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px #0002; padding: 2.5rem 2rem; }
        .profile-avatar-wrapper { position: relative; display: inline-block; }
        .profile-avatar { width: 130px; height: 130px; object-fit: cover; border-radius: 50%; border: 5px solid #00572d; background: #e9ecef; }
        .profile-avatar-overlay {
            position: absolute; bottom: 0; right: 0; background: #00572d; color: #fff; border-radius: 50%; padding: 8px; cursor: pointer; border: 2px solid #fff;
        }
        .form-label { color: #00572d; font-weight: 500; }
        .btn-primary { background: #00572d; border: none; font-weight: 600; }
        .btn-primary:hover, .btn-primary:focus { background: #1f9345; }
        .badge-role { background: #f3c300; color: #00572d; font-size: 1rem; }
        .readonly { background: #e9ecef; cursor: not-allowed; }
        .section-title { color: #1f9345; font-size: 1.1rem; font-weight: 600; margin-top: 2rem; margin-bottom: 1rem; letter-spacing: 0.5px; }
        .input-group-text { background: #f3c300; color: #00572d; border: none; }
        @media (max-width: 600px) {
            .profile-card { padding: 1rem; }
            .profile-avatar { width: 90px; height: 90px; }
        }
        .spinner-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(255,255,255,0.7); z-index: 9999; align-items: center; justify-content: center;
        }
        .spinner-border { color: #00572d; }
        .toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 1055; }
    </style>
</head>
<body>
<div class="spinner-overlay" id="loadingSpinner">
    <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
</div>
<div class="toast-container" id="toastContainer"></div>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="profile-card">
                <div class="d-flex align-items-center mb-4 flex-column flex-md-row">
                    <div class="profile-avatar-wrapper me-md-4 mb-3 mb-md-0">
                        <img id="profileImgPreview" src="<?= ($user['profile_image']) ? '/dutsca/assets/images/profile/' . htmlspecialchars($user['profile_image']) : 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&background=00572d&color=fff&size=120' ?>" class="profile-avatar">
                        <label for="profileImageInput" class="profile-avatar-overlay" title="Change photo"><i class="fa fa-camera"></i></label>
                        <input type="file" id="profileImageInput" name="profile_image" class="d-none" accept="image/*">
                    </div>
                    <div>
                        <h3 class="mb-0" style="color:#00572d;">My Profile</h3>
                        <span class="badge badge-role"> <?= htmlspecialchars(ucfirst($user['role'])) ?> </span>
                        <span class="badge <?= $user['status']==='active'?'bg-success':'bg-danger' ?> ms-2"> <?= ucfirst($user['status']) ?> </span>
                        <div class="text-muted mt-2">Last login: <?= $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never' ?></div>
                    </div>
                </div>
                <form method="post" enctype="multipart/form-data" autocomplete="off" id="profileForm">
                    <div class="section-title"><i class="fa fa-user me-2"></i>Personal Info</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-user"></i></span>
                                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <div class="input-group" title="Email cannot be changed">
                                <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                                <input type="email" class="form-control readonly" value="<?= htmlspecialchars($user['email']) ?>" readonly tabindex="-1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-phone"></i></span>
                                <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($user['contact_number']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-building"></i></span>
                                <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($user['department']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Position</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-briefcase"></i></span>
                                <input type="text" name="position" class="form-control" value="<?= htmlspecialchars($user['position']) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="section-title"><i class="fa fa-university me-2"></i>Bank Info</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Bank Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-university"></i></span>
                                <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($user['bank_name']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank Branch</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-location-dot"></i></span>
                                <input type="text" name="bank_branch" class="form-control" value="<?= htmlspecialchars($user['bank_branch']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa fa-hashtag"></i></span>
                                <input type="text" name="account_number" class="form-control" value="<?= htmlspecialchars($user['account_number']) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="section-title"><i class="fa fa-id-card me-2"></i>Membership Info</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Membership Number</label>
                            <div class="input-group" title="Membership number cannot be changed">
                                <span class="input-group-text"><i class="fa fa-id-badge"></i></span>
                                <input type="text" class="form-control readonly" value="<?= htmlspecialchars($user['membership_number']) ?>" readonly tabindex="-1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Membership Date</label>
                            <div class="input-group" title="Membership date cannot be changed">
                                <span class="input-group-text"><i class="fa fa-calendar"></i></span>
                                <input type="text" class="form-control readonly" value="<?= htmlspecialchars($user['membership_date']) ?>" readonly tabindex="-1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Credit Eligible</label>
                            <div class="input-group" title="Credit eligibility is system managed">
                                <span class="input-group-text"><i class="fa fa-check-circle"></i></span>
                                <input type="text" class="form-control readonly" value="<?= $user['credit_eligible'] ? 'Yes' : 'No' ?>" readonly tabindex="-1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Credit Score</label>
                            <div class="input-group" title="Credit score is system managed">
                                <span class="input-group-text"><i class="fa fa-star"></i></span>
                                <input type="text" class="form-control readonly" value="<?= htmlspecialchars($user['credit_score']) ?>" readonly tabindex="-1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Monthly Contribution</label>
                            <div class="input-group" title="Monthly contribution is system managed">
                                <span class="input-group-text"><i class="fa fa-money-bill"></i></span>
                                <input type="text" class="form-control readonly" value="₦<?= number_format($user['monthly_contribution'],2) ?>" readonly tabindex="-1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total Contribution</label>
                            <div class="input-group" title="Total contribution is system managed">
                                <span class="input-group-text"><i class="fa fa-coins"></i></span>
                                <input type="text" class="form-control readonly" value="₦<?= number_format($user['total_contribution'],2) ?>" readonly tabindex="-1">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Available Balance</label>
                            <div class="input-group" title="Available balance is system managed">
                                <span class="input-group-text"><i class="fa fa-wallet"></i></span>
                                <input type="text" class="form-control readonly" value="₦<?= number_format($user['available_balance'],2) ?>" readonly tabindex="-1">
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary px-4" id="submitBtn"><i class="fa fa-save me-2"></i>Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Profile image preview
const profileImgPreview = document.getElementById('profileImgPreview');
const profileImageInput = document.getElementById('profileImageInput');
if (profileImageInput) {
    profileImageInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                profileImgPreview.src = e.target.result;
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
}
// Show loading spinner on submit
const profileForm = document.getElementById('profileForm');
const loadingSpinner = document.getElementById('loadingSpinner');
if (profileForm) {
    profileForm.addEventListener('submit', function() {
        loadingSpinner.style.display = 'flex';
    });
}
// Toast notification function
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 mb-2 show`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    toastContainer.appendChild(toast);
    setTimeout(() => { toast.remove(); }, 4000);
}
// Show PHP feedback as toast
<?php if ($success): ?>showToast("<?= addslashes($success) ?>", 'success');<?php endif; ?>
<?php if ($error): ?>showToast("<?= addslashes($error) ?>", 'danger');<?php endif; ?>
</script>
</body>
</html> 