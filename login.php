<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/translations.php';

// Set language to Russian
$_SESSION['language'] = 'ru';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /');
    exit;
}

$error = '';
$success = '';

// Show logout success message
if (isset($_GET['loggedout'])) {
    $success = getTranslation('You have been logged out. Operation successful.', 'ru');
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = getTranslation('Please enter both username and password.', 'ru');
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, password_hash, role, is_active FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                if (!$user['is_active']) {
                    $error = getTranslation('Your account has been deactivated. Please contact an administrator.', 'ru');
                } else {
                    loginUser($user['id'], $remember);
                    
                    // Redirect based on role
                    $redirect = $_GET['redirect'] ?? '/';
                    header('Location: ' . $redirect);
                    exit;
                }
            } else {
                $error = getTranslation('Invalid username or password.', 'ru');
                logActivity(null, 'LOGIN_FAILED', "Failed login attempt for: $username");
            }
        } catch (PDOException $e) {
            $error = getTranslation('An error occurred. Please try again later.', 'ru');
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    logoutUser();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getTranslation('Login', 'ru'); ?> - AGKB College</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            padding: 40px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            font-weight: 700;
            color: white;
        }
        
        .login-header h1 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #64748b;
            font-size: 14px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #4b5563;
            cursor: pointer;
        }
        
        .remember-me input {
            width: 16px;
            height: 16px;
            accent-color: #3b82f6;
        }
        
        .forgot-password {
            font-size: 14px;
            color: #3b82f6;
            text-decoration: none;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4);
        }
        
        .demo-accounts {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }
        
        .demo-accounts h3 {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 16px;
            text-align: center;
        }
        
        .demo-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .demo-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f9fafb;
            border-radius: 8px;
            font-size: 13px;
        }
        
        .demo-role {
            font-weight: 600;
            color: #1f2937;
        }
        
        .demo-creds {
            color: #6b7280;
            font-family: monospace;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">A</div>
            <h1><?php echo getTranslation('Welcome to AGKB College', 'ru'); ?></h1>
            <p><?php echo getTranslation('Sign in to access your dashboard', 'ru'); ?></p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username"><?php echo getTranslation('Username or Email', 'ru'); ?></label>
                <input type="text" id="username" name="username" required 
                       placeholder="<?php echo getTranslation('Enter your username or email', 'ru'); ?>"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password"><?php echo getTranslation('Password', 'ru'); ?></label>
                <input type="password" id="password" name="password" required 
                       placeholder="<?php echo getTranslation('Enter your password', 'ru'); ?>"
            </div>
            
            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember">
                    <span><?php echo getTranslation('Remember me', 'ru'); ?></span>
                </label>
                <a href="#" class="forgot-password"><?php echo getTranslation('Forgot Password?', 'ru'); ?></a>
            </div>
            
            <button type="submit" class="btn-login"><?php echo getTranslation('Sign In', 'ru'); ?></button>
        </form>
        
        <div class="login-link" style="text-align: center; margin-top: 24px; padding-top: 24px; border-top: 1px solid #e5e7eb; font-size: 14px; color: #6b7280;">
            <?php echo getTranslation('Do not have an account?', 'ru'); ?> <a href="/register.php" style="color: #3b82f6; text-decoration: none; font-weight: 500;"><?php echo getTranslation('Create one', 'ru'); ?></a>
        </div>
        
    </div>
</body>
</html>
