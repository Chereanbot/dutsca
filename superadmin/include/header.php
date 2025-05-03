<?php
// Check if session is already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: /dutsca/index.php');
    exit();
}

// Database connection
require_once('../config/database.php');
$db = getDB();

// Get user data from session
$userRole = $_SESSION['role'] ?? 'superadmin';
$userName = $_SESSION['name'] ?? 'System Admin';
$userId = $_SESSION['user_id'] ?? null;

// Get user profile image
$profileImage = 'default-avatar.png'; // Default image
if ($userId) {
    try {
        $result = $db->fetchOne("SELECT profile_image FROM users WHERE id = ?", [$userId]);
        if ($result && isset($result['profile_image'])) {
            $profileImage = $result['profile_image'];
        }
    } catch (Exception $e) {
        error_log("Error fetching profile image: " . $e->getMessage());
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DUTSCA Admin</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">
    
    <!-- Toastify CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

    <style>
        :root {
            --primary-color: #00572d;
            --secondary-color: #1f9345;
            --accent-color: #f3c300;
            --sidebar-width: 256px;
            --header-height: 60px;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: #f4f4f4;
        }

        /* Header Styles */
        .main-header {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--header-height);
            background: var(--primary-color);
            color: white;
            z-index: 1000;
            display: flex;
            align-items: center;
            padding: 0 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .brand-logo {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .brand-logo img {
            height: 40px;
            margin-right: 10px;
        }

        .user-menu {
            margin-left: auto;
            display: flex;
            align-items: center;
        }

        .user-menu .dropdown-toggle {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 10px;
        }

        .user-menu .dropdown-toggle:hover {
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
        }

        .profile-image {
            width: 32px;
            height: 32px;
            object-fit: cover;
        }

        .dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .dropdown-header .profile-image {
            width: 64px;
            height: 64px;
            object-fit: cover;
        }

        .dropdown-header h6 {
            color: #333;
            margin-bottom: 0.25rem;
        }

        .dropdown-header small {
            color: #666;
        }

        .user-menu .dropdown-toggle img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .user-menu .dropdown-menu {
            margin-top: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .user-menu .dropdown-item {
            padding: 8px 20px;
            color: #333;
        }

        .user-menu .dropdown-item i {
            margin-right: 10px;
            color: var(--primary-color);
        }

        /* Content Wrapper */
        .content-wrapper {
            margin-left: var(--sidebar-width);
            padding-top: var(--header-height);
            min-height: calc(100vh - var(--header-height));
            background: #f4f4f4;
        }

        .content-header {
            padding: 15px;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .content {
            padding: 20px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 0px;
            }

            .main-header {
                left: 0;
            }

            .content-wrapper {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Main Header -->
    <header class="main-header">
        <a href="dashboard.php" class="brand-logo">
            <img src="../assets/images/logo.png" alt="DUTSCA Logo">
            <span>DUTSCA Admin</span>
        </a>

        <div class="user-menu">
            <div class="dropdown">
                <button class="btn dropdown-toggle" type="button" id="userDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <img src="../assets/images/profile/<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="profile-image rounded-circle">
                    <span class="ml-2"><?php echo htmlspecialchars($userName); ?></span>
                </button>
                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                    <div class="dropdown-header text-center">
                        <img src="../assets/images/profile/<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="profile-image rounded-circle mb-2">
                        <h6 class="mb-0"><?php echo htmlspecialchars($userName); ?></h6>
                        <small><?php echo htmlspecialchars($userRole); ?></small>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="profile.php">
                        <i class="fas fa-user-cog mr-2"></i>Profile
                    </a>
                    <a class="dropdown-item" href="settings.php">
                        <i class="fas fa-cog mr-2"></i>Settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="logout.php">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>
    
    <!-- Toastify JS -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
</body>
</html>
