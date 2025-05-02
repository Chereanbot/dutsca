<?php
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Add your authentication logic here
    // For now, using a simple example
    if ($email === 'admin@example.com' && $password === 'password') {
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'superadmin';
        header('Location: /index.php');
        exit();
    } else {
        $error = 'Invalid email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DUTSCA - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .login-container {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
        }
    </style>
</head>
<body>
    <div class="min-h-screen flex items-center justify-center login-container">
        <div class="max-w-md w-full mx-4">
            <div class="card bg-white p-8">
                <div class="text-center mb-8">
                    <h1 class="text-2xl font-bold text-gray-800">Welcome to DUTSCA</h1>
                    <p class="text-gray-600 mt-2">Please sign in to continue</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error mb-6">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="/login.php">
                    <div class="mb-6">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <div class="relative">
                            <span class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" id="email" name="email" required
                                class="form-input pl-10"
                                placeholder="Enter your email">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <div class="relative">
                            <span class="absolute left-3 top-3 text-gray-400">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" id="password" name="password" required
                                class="form-input pl-10"
                                placeholder="Enter your password">
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <input type="checkbox" id="remember" name="remember"
                                class="h-4 w-4 text-primary-green border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-700">
                                Remember me
                            </label>
                        </div>
                        <a href="/forgot-password.php" class="text-sm text-primary-green hover:text-secondary-green">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit" class="btn-primary w-full flex justify-center">
                        Sign In
                    </button>
                </form>

                <div class="mt-6 text-center text-sm">
                    <p class="text-gray-600">
                        Don't have an account? 
                        <a href="/register.php" class="text-primary-green hover:text-secondary-green font-medium">
                            Register here
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 