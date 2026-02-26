<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';

requireAuth();
$currentUser = getCurrentUser();
if (!$currentUser) {
    logoutUser();
    header('Location: /login.php');
    exit;
}

$userRole = $currentUser['role'];
$userName = $currentUser['first_name'] . ' ' . $currentUser['last_name'];
$userAvatar = $currentUser['avatar'] ?: strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1));

if ($userRole !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    header('Location: /?error=unauthorized');
    exit;
}

$pdo = getDBConnection();
$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Create new student group
        if ($_POST['action'] === 'create_group') {
            $groupName = $_POST['group_name'] ?? '';
            $courseId = $_POST['course_id'] ?? '';
            $teacherId = $_POST['teacher_id'] ?? '';
            $academicYear = $_POST['academic_year'] ?? date('Y');
            $maxStudents = $_POST['max_students'] ?? 30;
            
            try {
                $stmt = $pdo->prepare("INSERT INTO classes (course_id, teacher_id, semester, academic_year, schedule, room, max_students) VALUES (?, ?, 'Group', ?, ?, '', ?)");
                $stmt->execute([$courseId, $teacherId, $academicYear, $groupName, $maxStudents]);
                $message = 'Student group created successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error creating group: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
        // Enroll student in class
        if ($_POST['action'] === 'enroll_student') {
            $studentId = $_POST['student_id'] ?? '';
            $classId = $_POST['class_id'] ?? '';
            
            try {
                $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, class_id, status) VALUES (?, ?, 'active')");
                $stmt->execute([$studentId, $classId]);
                $message = 'Student enrolled successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $message = 'Student is already enrolled in this class.';
                } else {
                    $message = 'Error enrolling student: ' . $e->getMessage();
                }
                $messageType = 'error';
            }
        }
        
        // Remove student from class
        if ($_POST['action'] === 'remove_enrollment') {
            $enrollmentId = $_POST['enrollment_id'] ?? '';
            
            try {
                $stmt = $pdo->prepare("DELETE FROM enrollments WHERE id = ?");
                $stmt->execute([$enrollmentId]);
                $message = 'Student removed from class successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error removing student: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
        // Create new course
        if ($_POST['action'] === 'create_course') {
            $courseCode = $_POST['course_code'] ?? '';
            $courseName = $_POST['course_name'] ?? '';
            $description = $_POST['description'] ?? '';
            $credits = $_POST['credits'] ?? 3;
            $department = $_POST['department'] ?? '';
            
            try {
                $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, description, credits, department) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$courseCode, $courseName, $description, $credits, $department]);
                $message = 'Course created successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error creating course: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
        // Delete class
        if ($_POST['action'] === 'delete_class') {
            $classId = $_POST['class_id'] ?? '';
            
            try {
                $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
                $stmt->execute([$classId]);
                $message = 'Class deleted successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error deleting class: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Get all students
$stmt = $pdo->query("SELECT s.id, s.student_id, u.first_name, u.last_name, u.email, s.grade_level, s.major 
                     FROM students s 
                     JOIN users u ON s.user_id = u.id 
                     ORDER BY u.last_name, u.first_name");
$students = $stmt->fetchAll();

// Get all courses
$stmt = $pdo->query("SELECT * FROM courses WHERE is_active = TRUE ORDER BY course_code");
$courses = $stmt->fetchAll();

// Get all teachers
$stmt = $pdo->query("SELECT t.id, u.first_name, u.last_name, t.department 
                     FROM teachers t 
                     JOIN users u ON t.user_id = u.id 
                     ORDER BY u.last_name, u.first_name");
$teachers = $stmt->fetchAll();

// Get all classes with details
$stmt = $pdo->query("SELECT c.*, co.course_name, co.course_code, co.credits, u.first_name, u.last_name,
                     (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id AND e.status = 'active') as enrolled_count
                     FROM classes c 
                     JOIN courses co ON c.course_id = co.id 
                     JOIN teachers t ON c.teacher_id = t.id
                     JOIN users u ON t.user_id = u.id
                     ORDER BY c.academic_year DESC, c.semester DESC, co.course_code");
$classes = $stmt->fetchAll();

// Get selected class details
$selectedClass = null;
$classStudents = [];
$availableStudents = [];

if (isset($_GET['class_id'])) {
    $classId = $_GET['class_id'];
    
    // Get class details
    $stmt = $pdo->prepare("SELECT c.*, co.course_name, co.course_code, co.credits, u.first_name, u.last_name
                          FROM classes c 
                          JOIN courses co ON c.course_id = co.id 
                          JOIN teachers t ON c.teacher_id = t.id
                          JOIN users u ON t.user_id = u.id
                          WHERE c.id = ?");
    $stmt->execute([$classId]);
    $selectedClass = $stmt->fetch();
    
    // Get enrolled students
    $stmt = $pdo->prepare("SELECT e.id as enrollment_id, s.id as student_id, s.student_id as student_code, 
                          u.first_name, u.last_name, u.email, s.grade_level, e.enrollment_date
                          FROM enrollments e 
                          JOIN students s ON e.student_id = s.id 
                          JOIN users u ON s.user_id = u.id 
                          WHERE e.class_id = ? AND e.status = 'active'
                          ORDER BY u.last_name, u.first_name");
    $stmt->execute([$classId]);
    $classStudents = $stmt->fetchAll();
    
    // Get available students (not enrolled in this class)
    $stmt = $pdo->prepare("SELECT s.id, s.student_id, u.first_name, u.last_name, u.email, s.grade_level
                          FROM students s 
                          JOIN users u ON s.user_id = u.id 
                          WHERE s.id NOT IN (SELECT student_id FROM enrollments WHERE class_id = ? AND status = 'active')
                          ORDER BY u.last_name, u.first_name");
    $stmt->execute([$classId]);
    $availableStudents = $stmt->fetchAll();
}

$navItems = getNavigationItems($userRole);
$pageTitle = 'Student Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - AGKB College</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .page-content { background: white; border-radius: 12px; padding: 24px; min-height: 400px; }
        .page-content h1 { font-size: 24px; color: var(--gray-800); margin-bottom: 24px; }
        
        .message { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; }
        .message.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .message.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; }
        .tab { padding: 8px 16px; border: none; background: transparent; cursor: pointer; font-size: 14px; color: #6b7280; border-radius: 6px; }
        .tab:hover { background: #f3f4f6; }
        .tab.active { background: #1e40af; color: white; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .btn { 
            padding: 8px 16px; 
            border: none; 
            border-radius: 6px; 
            font-size: 14px; 
            cursor: pointer; 
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        .section { margin-bottom: 32px; }
        .section h2 { font-size: 18px; color: var(--gray-800); margin-bottom: 16px; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #e5e7eb; 
        }
        .data-table th { 
            background: #f9fafb; 
            font-weight: 600; 
            color: #374151; 
            font-size: 12px;
            text-transform: uppercase;
        }
        .data-table tr:hover { background: #f9fafb; }
        
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content { 
            background: white; 
            border-radius: 12px; 
            padding: 24px; 
            width: 90%; 
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header { margin-bottom: 20px; }
        .modal-header h2 { font-size: 20px; color: var(--gray-800); }
        .modal-close { 
            float: right; 
            font-size: 24px; 
            cursor: pointer; 
            color: #6b7280; 
            background: none;
            border: none;
        }
        .modal-close:hover { color: #374151; }
        
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 14px; font-weight: 500; color: #374151; }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 10px 12px; 
            border: 1px solid #d1d5db; 
            border-radius: 6px; 
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        
        .class-card { 
            background: #f9fafb; 
            border-radius: 12px; 
            padding: 16px; 
            margin-bottom: 12px;
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .class-info h3 { font-size: 16px; color: #1f2937; margin-bottom: 4px; }
        .class-info p { font-size: 13px; color: #6b7280; }
        .class-stats { text-align: right; }
        .class-stats .count { font-size: 24px; font-weight: 700; color: #1e40af; }
        .class-stats .label { font-size: 12px; color: #6b7280; }
        
        .empty-state { text-align: center; padding: 48px 24px; color: #6b7280; }
        .empty-state h3 { font-size: 18px; margin-bottom: 8px; color: #374151; }
        
        .actions { display: flex; gap: 8px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #f9fafb; padding: 16px; border-radius: 8px; text-align: center; }
        .stat-card h4 { font-size: 12px; color: #6b7280; margin-bottom: 8px; text-transform: uppercase; }
        .stat-card p { font-size: 28px; font-weight: 700; color: #1e40af; }
        
        .enrollment-section { 
            background: #f9fafb; 
            border-radius: 12px; 
            padding: 20px; 
            margin-top: 24px;
        }
        .enrollment-section h3 { font-size: 16px; color: #1f2937; margin-bottom: 16px; }
        
        .student-list { max-height: 300px; overflow-y: auto; }
        .student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: white;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid #e5e7eb;
        }
        .student-item:hover { border-color: #3b82f6; }
        
        .badge { 
            display: inline-block; 
            padding: 2px 8px; 
            border-radius: 12px; 
            font-size: 11px;
            font-weight: 500;
        }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-gray { background: #f3f4f6; color: #6b7280; }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">A</div>
                    <div class="logo-text">
                        <span class="logo-title">AGKB</span>
                        <span class="logo-subtitle">College</span>
                    </div>
                </div>
                <button class="toggle-btn" id="sidebarToggle">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="role-indicator"><?php echo ucfirst($userRole); ?> Panel</div>
            <nav class="sidebar-nav">
                <a href="/" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span class="nav-label">Dashboard</span>
                </a>
                <div class="nav-sections">
                    <?php foreach ($navItems as $item): ?>
                        <a href="<?php echo htmlspecialchars($item['path']); ?>" class="nav-item <?php echo $item['label'] === $pageTitle ? 'active' : ''; ?>">
                            <?php echo $item['icon']; ?>
                            <span class="nav-label"><?php echo htmlspecialchars($item['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </nav>
            <div class="sidebar-footer">
                <span class="version">Version 1.0.0</span>
            </div>
        </aside>
        <div class="main-wrapper">
            <header class="header">
                <div class="header-left">
                    <h1 style="font-size: 20px; font-weight: 600;"><?php echo $pageTitle; ?></h1>
                </div>
                <div class="header-right">
                    <div class="user-menu">
                        <div class="avatar <?php echo $userRole; ?>"><?php echo htmlspecialchars($userAvatar); ?></div>
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                            <span class="user-role"><?php echo ucfirst($userRole); ?></span>
                        </div>
                    </div>
                    <a href="/logout.php" class="logout-btn" title="Logout">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </a>
                </div>
            </header>
            <main class="main-content">
                <div class="page-content">
                    <h1><?php echo $pageTitle; ?></h1>
                    
                    <?php if ($message): ?>
                        <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h4>Total Students</h4>
                            <p><?php echo count($students); ?></p>
                        </div>
                        <div class="stat-card">
                            <h4>Total Classes</h4>
                            <p><?php echo count($classes); ?></p>
                        </div>
                        <div class="stat-card">
                            <h4>Total Courses</h4>
                            <p><?php echo count($courses); ?></p>
                        </div>
                        <div class="stat-card">
                            <h4>Teachers</h4>
                            <p><?php echo count($teachers); ?></p>
                        </div>
                    </div>
                    
                    <div class="tabs">
                        <button class="tab active" onclick="showTab('groups')">Student Groups</button>
                        <button class="tab" onclick="showTab('students')">All Students</button>
                        <button class="tab" onclick="showTab('courses')">Courses</button>
                    </div>
                    
                    <!-- Student Groups Tab -->
                    <div id="groups-tab" class="tab-content active">
                        <div class="section">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                <h2>All Student Groups</h2>
                                <button class="btn btn-primary" onclick="openModal('createGroupModal')">+ Create Group</button>
                            </div>
                            
                            <?php if (count($classes) > 0): ?>
                                <?php foreach ($classes as $class): ?>
                                    <div class="class-card">
                                        <div class="class-info">
                                            <h3><?php echo htmlspecialchars($class['schedule'] ?: 'Group'); ?> - <?php echo htmlspecialchars($class['course_name']); ?></h3>
                                            <p>
                                                <span class="badge badge-blue"><?php echo htmlspecialchars($class['academic_year']); ?></span>
                                                <span class="badge badge-gray"><?php echo htmlspecialchars($class['first_name'] . ' ' . $class['last_name']); ?></span>
                                                <span class="badge badge-green"><?php echo htmlspecialchars($class['course_code']); ?></span>
                                            </p>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 16px;">
                                            <div class="class-stats">
                                                <div class="count"><?php echo $class['enrolled_count']; ?>/<?php echo $class['max_students']; ?></div>
                                                <div class="label">Students</div>
                                            </div>
                                            <div class="actions">
                                                <a href="?class_id=<?php echo $class['id']; ?>" class="btn btn-secondary btn-sm">Manage</a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this group?');">
                                                    <input type="hidden" name="action" value="delete_class">
                                                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <h3>No Student Groups Created</h3>
                                    <p>Create your first group to start enrolling students.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($selectedClass): ?>
                            <div class="enrollment-section">
                                <h3>Managing: <?php echo htmlspecialchars($selectedClass['schedule'] ?: 'Group'); ?> - <?php echo htmlspecialchars($selectedClass['course_name']); ?></h3>
                                <p style="color: #6b7280; margin-bottom: 16px;">
                                    Course: <?php echo htmlspecialchars($selectedClass['course_code']); ?> • 
                                    Year: <?php echo htmlspecialchars($selectedClass['academic_year']); ?> • 
                                    Teacher: <?php echo htmlspecialchars($selectedClass['first_name'] . ' ' . $selectedClass['last_name']); ?>
                                </p>
                                
                                <div class="form-row">
                                    <div style="flex: 1;">
                                        <h4 style="margin-bottom: 12px;">Enrolled Students (<?php echo count($classStudents); ?>)</h4>
                                        <div class="student-list">
                                            <?php if (count($classStudents) > 0): ?>
                                                <?php foreach ($classStudents as $student): ?>
                                                    <div class="student-item">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></strong>
                                                            <div style="font-size: 12px; color: #6b7280;">
                                                                <?php echo htmlspecialchars($student['student_code']); ?> • Grade <?php echo $student['grade_level']; ?>
                                                            </div>
                                                        </div>
                                                        <form method="POST" onsubmit="return confirm('Remove this student from the class?');">
                                                            <input type="hidden" name="action" value="remove_enrollment">
                                                            <input type="hidden" name="enrollment_id" value="<?php echo $student['enrollment_id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p style="color: #6b7280; text-align: center; padding: 20px;">No students enrolled yet.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div style="flex: 1;">
                                        <h4 style="margin-bottom: 12px;">Add Students</h4>
                                        <div class="student-list">
                                            <?php if (count($availableStudents) > 0): ?>
                                                <?php foreach ($availableStudents as $student): ?>
                                                    <div class="student-item">
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></strong>
                                                            <div style="font-size: 12px; color: #6b7280;">
                                                                <?php echo htmlspecialchars($student['student_id']); ?> • Grade <?php echo $student['grade_level']; ?>
                                                            </div>
                                                        </div>
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="enroll_student">
                                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                            <input type="hidden" name="class_id" value="<?php echo $selectedClass['id']; ?>">
                                                            <button type="submit" class="btn btn-success btn-sm">Add</button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p style="color: #6b7280; text-align: center; padding: 20px;">All students are enrolled.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Students Tab -->
                    <div id="students-tab" class="tab-content">
                        <div class="section">
                            <h2>All Students</h2>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Grade Level</th>
                                        <th>Major</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                            <td><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td><?php echo $student['grade_level']; ?></td>
                                            <td><?php echo htmlspecialchars($student['major'] ?: 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Courses Tab -->
                    <div id="courses-tab" class="tab-content">
                        <div class="section">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                <h2>All Courses</h2>
                                <button class="btn btn-primary" onclick="openModal('createCourseModal')">+ Add Course</button>
                            </div>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Department</th>
                                        <th>Credits</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                            <td><?php echo htmlspecialchars($course['department']); ?></td>
                                            <td><?php echo $course['credits']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Create Student Group Modal -->
    <div id="createGroupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('createGroupModal')">&times;</button>
                <h2>Create New Student Group</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_group">
                
                <div class="form-group">
                    <label for="group_name">Group Name</label>
                    <input type="text" name="group_name" id="group_name" required placeholder="e.g., Group A, Morning Group">
                </div>
                
                <div class="form-group">
                    <label for="course_id">Course</label>
                    <select name="course_id" id="course_id" required>
                        <option value="">-- Select Course --</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="teacher_id">Teacher</label>
                    <select name="teacher_id" id="teacher_id" required>
                        <option value="">-- Select Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name'] . ' (' . $teacher['department'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="academic_year">Academic Year</label>
                        <input type="number" name="academic_year" id="academic_year" required value="<?php echo date('Y'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="max_students">Max Students</label>
                        <input type="number" name="max_students" id="max_students" value="30" min="1">
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createGroupModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Group</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Create Course Modal -->
    <div id="createCourseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('createCourseModal')">&times;</button>
                <h2>Add New Course</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_course">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_code">Course Code</label>
                        <input type="text" name="course_code" id="course_code" required placeholder="e.g., CS101">
                    </div>
                    <div class="form-group">
                        <label for="credits">Credits</label>
                        <input type="number" name="credits" id="credits" required value="3" min="1" max="6">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="course_name">Course Name</label>
                    <input type="text" name="course_name" id="course_name" required placeholder="e.g., Introduction to Computer Science">
                </div>
                
                <div class="form-group">
                    <label for="department">Department</label>
                    <input type="text" name="department" id="department" required placeholder="e.g., Computer Science">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="3" placeholder="Course description..."></textarea>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createCourseModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Course</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
        
        function showTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
