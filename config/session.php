<?php
// Session Configuration

// Session security settings - MUST be set before session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user data
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    require_once __DIR__ . '/database.php';
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT id, username, email, role, first_name, last_name, avatar, last_login 
                           FROM users WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Require authentication
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

// Require specific role
function requireRole($roles) {
    requireAuth();
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    $user = getCurrentUser();
    if (!$user || !in_array($user['role'], $roles)) {
        header('HTTP/1.1 403 Forbidden');
        header('Location: /?error=unauthorized');
        exit;
    }
}

// Login user
function loginUser($userId, $remember = false) {
    require_once __DIR__ . '/database.php';
    
    $_SESSION['user_id'] = $userId;
    $_SESSION['login_time'] = time();
    
    // Update last login
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
    
    // Log activity
    logActivity($userId, 'LOGIN', 'User logged in successfully');
    
    // Set remember me cookie if requested
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $token, $expires]);
        
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', isset($_SERVER['HTTPS']), true);
    }
}

// Logout user
function logoutUser() {
    require_once __DIR__ . '/database.php';
    
    if (isset($_SESSION['user_id'])) {
        logActivity($_SESSION['user_id'], 'LOGOUT', 'User logged out');
    }
    
    // Clear remember token
    if (isset($_COOKIE['remember_token'])) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?");
        $stmt->execute([$_COOKIE['remember_token']]);
        
        setcookie('remember_token', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
    }
    
    // Clear session data
    $_SESSION = array();
    
    // Destroy session
    session_destroy();
}

// Check remember me cookie
function checkRememberToken() {
    if (isLoggedIn() || !isset($_COOKIE['remember_token'])) {
        return;
    }
    
    require_once __DIR__ . '/database.php';
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT user_id FROM user_sessions 
                           WHERE session_token = ? AND expires_at > NOW()");
    $stmt->execute([$_COOKIE['remember_token']]);
    $result = $stmt->fetch();
    
    if ($result) {
        loginUser($result['user_id'], true);
    }
}

// Check remember token on load
checkRememberToken();
