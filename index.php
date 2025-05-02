<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Initialize variables
$error = '';
$success = '';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            try {
                $db = getDB();
                $stmt = $db->executeQuery(
                    "SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1",
                    [$email]
                );
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Update last login
                    $db->executeQuery(
                        "UPDATE users SET last_login = NOW(), login_attempts = 0 WHERE id = ?",
                        [$user['id']]
                    );

                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['name'];

                    // Log the successful login
                    $db->executeQuery(
                        "INSERT INTO user_activity_logs (user_id, action, description, ip_address, user_agent) 
                         VALUES (?, 'login', 'Successful login', ?, ?)",
                        [$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]
                    );

                    if ($isAjax) {
                        // Return JSON response for AJAX requests
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'message' => 'Login successful',
                            'redirect' => $user['role'] . '/dashboard.php'
                        ]);
                        exit;
                    } else {
                        // Redirect based on role for normal form submission
                        $redirect = '';
                        switch ($user['role']) {
                            case 'superadmin':
                                $redirect = 'admin/dashboard.php';
                                break;
                            case 'chairman':
                                $redirect = 'chairman/dashboard.php';
                                break;
                            case 'manager':
                                $redirect = 'manager/dashboard.php';
                                break;
                            case 'finance':
                                $redirect = 'finance/dashboard.php';
                                break;
                            case 'teacher':
                                $redirect = 'teacher/dashboard.php';
                                break;
                            case 'servant':
                                $redirect = 'servant/dashboard.php';
                                break;
                            default:
                                $redirect = 'dashboard.php';
                        }
                        header('Location: ' . $redirect);
                        exit();
                    }
                } else {
                    // Increment login attempts
                    if ($user) {
                        $db->executeQuery(
                            "UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?",
                            [$user['id']]
                        );

                        // Lock account if too many attempts
                        if ($user['login_attempts'] >= 4) {
                            $db->executeQuery(
                                "UPDATE users SET status = 'suspended' WHERE id = ?",
                                [$user['id']]
                            );
                            $error = 'Account has been suspended due to too many failed attempts. Please contact support.';
                        } else {
                            $error = 'Invalid email or password.';
                        }
                    } else {
                        $error = 'Invalid email or password.';
                    }

                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => false,
                            'message' => $error
                        ]);
                        exit;
                    }
                }
            } catch (Exception $e) {
                $error = 'An error occurred. Please try again later.';
                error_log($e->getMessage());
                
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => $error
                    ]);
                    exit;
                }
            }
        }
    } else {
        $error = 'Invalid request. Please try again.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $error
            ]);
            exit;
        }
    }
}

// Only output HTML if not an AJAX request
if (!$isAjax):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DUTSCA - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-green: #00572d;
            --secondary-green: #1f9345;
            --accent-gold: #f3c300;
            --text-primary: #333333;
            --bg-light: #f4f4f4;
            --footer-dark: #1a1a1a;
        }
        .login-container {
            background:var(--primary-green);
        }
        .btn-primary {
            background-color: var(--primary-green);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: var(--secondary-green);
        }
        .input-focus-ring:focus {
            box-shadow: 0 0 0 3px rgba(0,87,45,0.2);
        }
        .hover-card {
            transition: transform 0.3s ease;
        }
        .hover-card:hover {
            transform: translateY(-2px);
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .toast {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .loading-overlay {
            background-color: rgba(49, 216, 16, 0.9);
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="hidden fixed inset-0 z-50 loading-overlay flex flex-col items-center justify-center">
        <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-[#f3c300] border-solid"></div>
        <p class="mt-4 text-[#00572d] font-semibold text-lg">Securely connecting to DUTSCA...</p>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-4"></div>

    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-white to-gray-50">
        <div class="login-container max-w-6xl w-full flex rounded-2xl shadow-2xl overflow-hidden">
            <!-- Logo Section -->
            <div class="hidden lg:flex lg:w-1/2 bg-[#00572d] flex-col items-center justify-center p-12 text-white relative overflow-hidden">
                <div class="absolute inset-0 bg-[#1f9345] opacity-20 pattern-grid-lg"></div>
                <div class="relative z-10">
                    <img src="assets/images/logo.png" alt="DUTSCA Logo" class="w-64 mb-8 hover-card">
                    <h1 class="text-4xl font-bold text-center mb-6 tracking-tight">Welcome to DUTSCA</h1>
                    <p class="text-center text-lg opacity-90 mb-8">Dire University Teachers Saving and Credit Association</p>
                    <div class="space-y-6 text-sm opacity-85">
                        <div class="flex items-center p-3 bg-white bg-opacity-10 rounded-lg hover-card">
                            <i class="fas fa-shield-alt w-8 text-[#f3c300]"></i>
                            <span class="ml-3">Secure Savings Management</span>
                        </div>
                        <div class="flex items-center p-3 bg-white bg-opacity-10 rounded-lg hover-card">
                            <i class="fas fa-hand-holding-usd w-8 text-[#f3c300]"></i>
                            <span class="ml-3">Easy Credit Access</span>
                        </div>
                        <div class="flex items-center p-3 bg-white bg-opacity-10 rounded-lg hover-card">
                            <i class="fas fa-users w-8 text-[#f3c300]"></i>
                            <span class="ml-3">Community Support</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Login Form Section -->
            <div class="w-full lg:w-1/2 bg-white px-8 lg:px-12 py-12">
                <div class="mb-10 text-center">
                    <h2 class="text-3xl font-bold text-[#00572d] mb-2">Sign in to your account</h2>
                    <p class="text-gray-600">
                        Access your DUTSCA dashboard securely
                    </p>
                </div>

                <?php if ($error): ?>
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg" role="alert">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <form class="space-y-6" method="POST" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="space-y-2">
                        <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-envelope text-[#1f9345]"></i>
                            </div>
                            <input id="email" name="email" type="email" required 
                                class="appearance-none block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg 
                                text-gray-900 placeholder-gray-500 input-focus-ring focus:outline-none focus:ring-2 
                                focus:ring-[#00572d] focus:border-[#00572d] transition duration-150 ease-in-out"
                                placeholder="Enter your email">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-[#1f9345]"></i>
                            </div>
                            <input id="password" name="password" type="password" required 
                                class="appearance-none block w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg 
                                text-gray-900 placeholder-gray-500 input-focus-ring focus:outline-none focus:ring-2 
                                focus:ring-[#00572d] focus:border-[#00572d] transition duration-150 ease-in-out"
                                placeholder="Enter your password">
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember_me" name="remember_me" type="checkbox" 
                                class="h-4 w-4 text-[#00572d] focus:ring-[#00572d] border-gray-300 rounded 
                                transition duration-150 ease-in-out">
                            <label for="remember_me" class="ml-2 block text-sm text-gray-700">
                                Remember me
                            </label>
                        </div>

                        <div class="text-sm">
                            <a href="forgot-password.php" class="font-medium text-[#00572d] hover:text-[#1f9345] 
                                transition duration-150 ease-in-out">
                                Forgot your password?
                            </a>
                        </div>
                    </div>

                    <div>
                        <button type="submit" 
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg 
                            shadow-sm text-sm font-medium text-white btn-primary hover:shadow-lg 
                            focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#00572d] 
                            transform transition duration-150 ease-in-out hover:scale-[1.02]">
                            <span class="mr-2">Sign in</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>

                <div class="mt-8">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">Need help?</span>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="rounded-lg bg-gray-50 p-4 hover-card">
                            <div class="text-center">
                                <p class="text-sm text-gray-600">
                                    Contact system administrator or call
                                </p>
                                <a href="tel:+251911223344" 
                                    class="mt-2 inline-flex items-center text-[#00572d] hover:text-[#1f9345] 
                                    font-medium transition duration-150 ease-in-out">
                                    <i class="fas fa-phone-alt mr-2"></i>
                                    +251 911 22 33 44
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toast notification function
        function showToast(type, message) {
            const container = document.getElementById('toastContainer');
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
                    borderColor = 'border-[#d93025]';
                    icon = '❌';
                    break;
                case 'info':
                    bgColor = 'bg-white';
                    borderColor = 'border-[#f3c300]';
                    icon = '⚠️';
                    break;
            }
            
            toast.className = `toast ${bgColor} border-l-4 ${borderColor} text-[#00572d] p-4 rounded shadow-lg flex items-start gap-2`;
            toast.innerHTML = `
                <span>${icon}</span>
                <div class="flex-1">
                    <p class="font-bold capitalize">${type}</p>
                    <p>${message}</p>
                </div>
                <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }

        // Form submission handling
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.remove('hidden');

            fetch(window.location.href, {
                method: 'POST',
                body: new FormData(this),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', data.message);
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                showToast('error', 'An error occurred. Please try again.');
                console.error('Error:', error);
            })
            .finally(() => {
                loadingOverlay.classList.add('hidden');
            });
        });

        // Password visibility toggle
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const togglePassword = document.createElement('button');
            togglePassword.type = 'button';
            togglePassword.className = 'absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-[#00572d] transition-colors duration-150';
            togglePassword.innerHTML = '<i class="fas fa-eye"></i>';
            togglePassword.onclick = function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            };
            passwordInput.parentElement.appendChild(togglePassword);
        });

        // Show error toast if PHP error exists
        <?php if ($error): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showToast('error', <?php echo json_encode($error); ?>);
            });
        <?php endif; ?>
    </script>
</body>
</html>
<?php endif; ?> 