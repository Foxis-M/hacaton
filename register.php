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

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $role = $_POST['role'] ?? 'student';
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword) || empty($firstName) || empty($lastName)) {
        $error = getTranslation('Please fill in all required fields.', 'ru');
    } elseif (strlen($username) < 3) {
        $error = getTranslation('Username must be at least 3 characters long.', 'ru');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = getTranslation('Please enter a valid email address.', 'ru');
    } elseif (strlen($password) < 6) {
        $error = getTranslation('Password must be at least 6 characters long.', 'ru');
    } elseif ($password !== $confirmPassword) {
        $error = getTranslation('Passwords do not match.', 'ru');
    } elseif (!in_array($role, ['student', 'teacher'])) {
        $error = getTranslation('Invalid role selected.', 'ru');
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = getTranslation('Username already exists.', 'ru');
            } else {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = getTranslation('Email already registered.', 'ru');
                } else {
                    // Hash password
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert user (inactive until approved by admin)
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name, is_active) 
                                           VALUES (?, ?, ?, ?, ?, ?, FALSE)");
                    $stmt->execute([$username, $email, $passwordHash, $role, $firstName, $lastName]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    // Create role-specific record
                    if ($role === 'student') {
                        $studentId = 'STU' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                        $gradeLevel = isset($_POST['grade_level']) ? intval($_POST['grade_level']) : 1;
                        try {
                            $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, grade_level, enrollment_date, gpa) 
                                                   VALUES (?, ?, ?, CURDATE(), 0.00)");
                            $stmt->execute([$userId, $studentId, $gradeLevel]);
                        } catch (PDOException $e) {
                            // If students table insert fails, delete the user to maintain consistency
                            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                            $stmt->execute([$userId]);
                            throw $e;
                        }
                    } elseif ($role === 'teacher') {
                        $teacherId = 'TCH' . str_pad($userId, 4, '0', STR_PAD_LEFT);
                        try {
                            $stmt = $pdo->prepare("INSERT INTO teachers (user_id, teacher_id, department, position, hire_date) 
                                                   VALUES (?, ?, 'General', 'Teacher', CURDATE())");
                            $stmt->execute([$userId, $teacherId]);
                        } catch (PDOException $e) {
                            // If teachers table insert fails, delete the user to maintain consistency
                            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                            $stmt->execute([$userId]);
                            throw $e;
                        }
                    }
                    
                    $success = getTranslation('Registration successful! Your account is pending admin approval. You will be able to log in once approved.', 'ru');
                }
            }
        } catch (PDOException $e) {
            $error = getTranslation('Error: ', 'ru') . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getTranslation('Register', 'ru'); ?> - AGKB College</title>
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
        
        .register-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 480px;
            padding: 40px;
        }
        
        .register-header {
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
        
        .register-header h1 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .register-header p {
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
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
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.2s;
            background: white;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn-register {
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
            margin-top: 8px;
        }
        
        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4);
        }
        
        .login-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
        }
        
        .login-link a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .register-container {
                padding: 24px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="logo">A</div>
            <h1><?php echo getTranslation('Create Account', 'ru'); ?></h1>
            <p><?php echo getTranslation('Join AGKB College today', 'ru'); ?></p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name"><?php echo getTranslation('First Name', 'ru'); ?> *</label>
                    <input type="text" id="first_name" name="first_name" required 
                           placeholder="<?php echo getTranslation('John', 'ru'); ?>"
                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="last_name"><?php echo getTranslation('Last Name', 'ru'); ?> *</label>
                    <input type="text" id="last_name" name="last_name" required 
                           placeholder="<?php echo getTranslation('Doe', 'ru'); ?>"
                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="username"><?php echo getTranslation('Username', 'ru'); ?> *</label>
                <input type="text" id="username" name="username" required 
                       placeholder="<?php echo getTranslation('Choose a username', 'ru'); ?>"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="email"><?php echo getTranslation('Email', 'ru'); ?> *</label>
                <input type="email" id="email" name="email" required 
                       placeholder="<?php echo getTranslation('your@email.com', 'ru'); ?>"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password"><?php echo getTranslation('Password', 'ru'); ?> *</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="<?php echo getTranslation('Min 6 characters', 'ru'); ?>"
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><?php echo getTranslation('Confirm Password', 'ru'); ?> *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="<?php echo getTranslation('Repeat password', 'ru'); ?>"
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="role"><?php echo getTranslation('I am a', 'ru'); ?> *</label>
                    <select id="role" name="role" required onchange="toggleGradeLevel()">
                        <option value="student" <?php echo ($_POST['role'] ?? '') === 'student' ? 'selected' : ''; ?>><?php echo getTranslation('Student', 'ru'); ?></option>
                        <option value="teacher" <?php echo ($_POST['role'] ?? '') === 'teacher' ? 'selected' : ''; ?>><?php echo getTranslation('Teacher', 'ru'); ?></option>
                    </select>
                </div>
                
                <div class="form-group" id="grade_level_group">
                    <label for="grade_level"><?php echo getTranslation('Grade Level', 'ru'); ?> *</label>
                    <select id="grade_level" name="grade_level">
                        <option value="1" <?php echo ($_POST['grade_level'] ?? '1') === '1' ? 'selected' : ''; ?>><?php echo getTranslation('1st Year', 'ru'); ?></option>
                        <option value="2" <?php echo ($_POST['grade_level'] ?? '') === '2' ? 'selected' : ''; ?>><?php echo getTranslation('2nd Year', 'ru'); ?></option>
                        <option value="3" <?php echo ($_POST['grade_level'] ?? '') === '3' ? 'selected' : ''; ?>><?php echo getTranslation('3rd Year', 'ru'); ?></option>
                        <option value="4" <?php echo ($_POST['grade_level'] ?? '') === '4' ? 'selected' : ''; ?>><?php echo getTranslation('4th Year', 'ru'); ?></option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn-register"><?php echo getTranslation('Create Account', 'ru'); ?></button>
        </form>
        
        <div class="login-link">
            <?php echo getTranslation('Already have an account?', 'ru'); ?> <a href="/login.php"><?php echo getTranslation('Sign In', 'ru'); ?></a>
        </div>
    </div>
    
    <script>
        function toggleGradeLevel() {
            const role = document.getElementById('role').value;
            const gradeLevelGroup = document.getElementById('grade_level_group');
            if (role === 'student') {
                gradeLevelGroup.style.display = 'block';
            } else {
                gradeLevelGroup.style.display = 'none';
            }
        }
        
        // Initialize on page load
        toggleGradeLevel();
    </script>
</body>
</html>
