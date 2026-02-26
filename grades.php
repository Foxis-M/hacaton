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

// Only teachers can access grade management
if ($userRole !== 'teacher') {
    header('HTTP/1.1 403 Forbidden');
    header('Location: /?error=unauthorized');
    exit;
}

$pdo = getDBConnection();

// Get teacher ID if user is a teacher
$teacherId = null;
if ($userRole === 'teacher') {
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
    $stmt->execute([$currentUser['id']]);
    $teacher = $stmt->fetch();
    if ($teacher) {
        $teacherId = $teacher['id'];
    }
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Add new grade
        if ($_POST['action'] === 'add_grade') {
            $enrollmentId = $_POST['enrollment_id'] ?? '';
            $assignmentName = $_POST['assignment_name'] ?? '';
            $assignmentType = $_POST['assignment_type'] ?? '';
            $score = $_POST['score'] ?? '';
            $maxScore = $_POST['max_score'] ?? 100;
            $comments = $_POST['comments'] ?? '';
            $classId = $_POST['class_id'] ?? '';
            
            // Verify the enrollment belongs to a class taught by this teacher
            $stmt = $pdo->prepare("SELECT e.id FROM enrollments e 
                                  JOIN classes c ON e.class_id = c.id 
                                  WHERE e.id = ? AND c.teacher_id = ? AND c.id = ?");
            $stmt->execute([$enrollmentId, $teacherId, $classId]);
            if (!$stmt->fetch()) {
                $message = 'Error: You can only grade students in your own groups.';
                $messageType = 'error';
            } else {
                // Calculate grade letter
                $gradeLetter = calculateGradeLetter($score, $maxScore);
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO grades (enrollment_id, assignment_name, assignment_type, score, max_score, grade_letter, graded_by, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$enrollmentId, $assignmentName, $assignmentType, $score, $maxScore, $gradeLetter, $teacherId, $comments]);
                    $message = 'Grade added successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error adding grade: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
        }
        
        // Update grade
        if ($_POST['action'] === 'update_grade') {
            $gradeId = $_POST['grade_id'] ?? '';
            $score = $_POST['score'] ?? '';
            $maxScore = $_POST['max_score'] ?? 100;
            $comments = $_POST['comments'] ?? '';
            
            // Verify the grade belongs to a class taught by this teacher
            $stmt = $pdo->prepare("SELECT g.id FROM grades g 
                                  JOIN enrollments e ON g.enrollment_id = e.id 
                                  JOIN classes c ON e.class_id = c.id 
                                  WHERE g.id = ? AND c.teacher_id = ?");
            $stmt->execute([$gradeId, $teacherId]);
            if (!$stmt->fetch()) {
                $message = 'Error: You can only update grades for your own groups.';
                $messageType = 'error';
            } else {
                $gradeLetter = calculateGradeLetter($score, $maxScore);
                
                try {
                    $stmt = $pdo->prepare("UPDATE grades SET score = ?, max_score = ?, grade_letter = ?, comments = ? WHERE id = ?");
                    $stmt->execute([$score, $maxScore, $gradeLetter, $comments, $gradeId]);
                    $message = 'Grade updated successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error updating grade: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
        }
        
        // Delete grade
        if ($_POST['action'] === 'delete_grade') {
            $gradeId = $_POST['grade_id'] ?? '';
            
            // Verify the grade belongs to a class taught by this teacher
            $stmt = $pdo->prepare("SELECT g.id FROM grades g 
                                  JOIN enrollments e ON g.enrollment_id = e.id 
                                  JOIN classes c ON e.class_id = c.id 
                                  WHERE g.id = ? AND c.teacher_id = ?");
            $stmt->execute([$gradeId, $teacherId]);
            if (!$stmt->fetch()) {
                $message = 'Error: You can only delete grades for your own groups.';
                $messageType = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM grades WHERE id = ?");
                    $stmt->execute([$gradeId]);
                    $message = 'Grade deleted successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error deleting grade: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
        }
    }
}

// Helper function to calculate grade letter
function calculateGradeLetter($score, $maxScore) {
    $percentage = ($score / $maxScore) * 100;
    if ($percentage >= 90) return 'A';
    if ($percentage >= 80) return 'B';
    if ($percentage >= 70) return 'C';
    if ($percentage >= 60) return 'D';
    return 'F';
}

// Get classes for the teacher - only their own groups
$stmt = $pdo->prepare("SELECT c.*, co.course_name, co.course_code, u.first_name, u.last_name 
                      FROM classes c 
                      JOIN courses co ON c.course_id = co.id 
                      JOIN teachers t ON c.teacher_id = t.id 
                      JOIN users u ON t.user_id = u.id 
                      WHERE c.teacher_id = ? 
                      ORDER BY co.course_name");
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll();

// Get selected class
$selectedClass = null;
$students = [];
$grades = [];

if (isset($_GET['class_id'])) {
    $classId = $_GET['class_id'];
    
    // Verify this class belongs to the teacher
    $stmt = $pdo->prepare("SELECT c.*, co.course_name, co.course_code 
                          FROM classes c 
                          JOIN courses co ON c.course_id = co.id 
                          WHERE c.id = ? AND c.teacher_id = ?");
    $stmt->execute([$classId, $teacherId]);
    $selectedClass = $stmt->fetch();
    
    // If class not found or doesn't belong to teacher, redirect
    if (!$selectedClass) {
        header('Location: /grades.php?error=unauthorized_class');
        exit;
    }
    
    // Get students in this class
    $stmt = $pdo->prepare("SELECT s.id as student_id, s.student_id as student_code, u.first_name, u.last_name, e.id as enrollment_id 
                          FROM enrollments e 
                          JOIN students s ON e.student_id = s.id 
                          JOIN users u ON s.user_id = u.id 
                          WHERE e.class_id = ? AND e.status = 'active' 
                          ORDER BY u.last_name, u.first_name");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();
    
    // Get grades for this class
    $stmt = $pdo->prepare("SELECT g.*, u.first_name, u.last_name, e.student_id 
                          FROM grades g 
                          JOIN enrollments e ON g.enrollment_id = e.id 
                          JOIN students s ON e.student_id = s.id 
                          JOIN users u ON s.user_id = u.id 
                          WHERE e.class_id = ? 
                          ORDER BY g.graded_at DESC");
    $stmt->execute([$classId]);
    $grades = $stmt->fetchAll();
}

$navItems = getNavigationItems($userRole);
$pageTitle = 'Grade Management';
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
        
        .class-selector { margin-bottom: 24px; }
        .class-selector select { 
            padding: 10px 16px; 
            border: 1px solid #e5e7eb; 
            border-radius: 8px; 
            font-size: 14px; 
            min-width: 300px;
            cursor: pointer;
        }
        
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; }
        .tab { padding: 8px 16px; border: none; background: transparent; cursor: pointer; font-size: 14px; color: #6b7280; border-radius: 6px; }
        .tab:hover { background: #f3f4f6; }
        .tab.active { background: #1e40af; color: white; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .students-table, .grades-table { width: 100%; border-collapse: collapse; }
        .students-table th, .students-table td, .grades-table th, .grades-table td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #e5e7eb; 
        }
        .students-table th, .grades-table th { 
            background: #f9fafb; 
            font-weight: 600; 
            color: #374151; 
            font-size: 12px;
            text-transform: uppercase;
        }
        .students-table tr:hover, .grades-table tr:hover { background: #f9fafb; }
        
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
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
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
            max-width: 500px;
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
        
        .grade-badge { 
            display: inline-block; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-weight: 600; 
            font-size: 12px;
        }
        .grade-a { background: #d1fae5; color: #065f46; }
        .grade-b { background: #dbeafe; color: #1e40af; }
        .grade-c { background: #fef3c7; color: #92400e; }
        .grade-d { background: #ffedd5; color: #9a3412; }
        .grade-f { background: #fee2e2; color: #991b1b; }
        
        .empty-state { text-align: center; padding: 48px 24px; color: #6b7280; }
        .empty-state h3 { font-size: 18px; margin-bottom: 8px; color: #374151; }
        
        .student-grades { margin-top: 8px; }
        .student-grade-item { 
            display: inline-block; 
            padding: 2px 8px; 
            margin: 2px; 
            border-radius: 4px; 
            font-size: 11px;
            background: #f3f4f6;
        }
        
        .actions { display: flex; gap: 8px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #f9fafb; padding: 16px; border-radius: 8px; }
        .stat-card h4 { font-size: 12px; color: #6b7280; margin-bottom: 8px; text-transform: uppercase; }
        .stat-card p { font-size: 24px; font-weight: 600; color: #1f2937; }
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
                    
                    <div class="class-selector">
                        <form method="GET" action="">
                            <label for="class_id" style="display: block; margin-bottom: 8px; font-weight: 500;">Select Class:</label>
                            <select name="class_id" id="class_id" onchange="this.form.submit()">
                                <option value="">-- Choose a Class --</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo isset($_GET['class_id']) && $_GET['class_id'] == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['course_code'] . ' - ' . $class['course_name'] . ' (' . $class['semester'] . ' ' . $class['academic_year'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    
                    <?php if ($selectedClass): ?>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <h4>Total Students</h4>
                                <p><?php echo count($students); ?></p>
                            </div>
                            <div class="stat-card">
                                <h4>Total Grades</h4>
                                <p><?php echo count($grades); ?></p>
                            </div>
                            <div class="stat-card">
                                <h4>Class Average</h4>
                                <p><?php 
                                    if (count($grades) > 0) {
                                        $total = 0;
                                        foreach ($grades as $grade) {
                                            $total += ($grade['score'] / $grade['max_score']) * 100;
                                        }
                                        echo round($total / count($grades), 1) . '%';
                                    } else {
                                        echo 'N/A';
                                    }
                                ?></p>
                            </div>
                        </div>
                        
                        <div class="tabs">
                            <button class="tab active" onclick="showTab('students')">Students</button>
                            <button class="tab" onclick="showTab('grades')">All Grades</button>
                        </div>
                        
                        <div id="students-tab" class="tab-content active">
                            <?php if (count($students) > 0): ?>
                                <table class="students-table">
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Name</th>
                                            <th>Grades</th>
                                            <th>Average</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): 
                                            $studentGrades = array_filter($grades, function($g) use ($student) {
                                                return $g['student_id'] == $student['student_id'];
                                            });
                                            $avg = 0;
                                            if (count($studentGrades) > 0) {
                                                $total = 0;
                                                foreach ($studentGrades as $g) {
                                                    $total += ($g['score'] / $g['max_score']) * 100;
                                                }
                                                $avg = $total / count($studentGrades);
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['student_code']); ?></td>
                                                <td><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></td>
                                                <td>
                                                    <div class="student-grades">
                                                        <?php foreach ($studentGrades as $g): ?>
                                                            <span class="student-grade-item" title="<?php echo htmlspecialchars($g['assignment_name']); ?>">
                                                                <?php echo $g['assignment_type']; ?>: <?php echo $g['score']; ?>/<?php echo $g['max_score']; ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($studentGrades) === 0): ?>
                                                            <span style="color: #9ca3af;">No grades yet</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (count($studentGrades) > 0): ?>
                                                        <span class="grade-badge grade-<?php echo strtolower(calculateGradeLetter($avg, 100)); ?>">
                                                            <?php echo round($avg, 1); ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-primary btn-sm" onclick="openAddGradeModal(<?php echo $student['enrollment_id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')">Add Grade</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-state">
                                    <h3>No Students Enrolled</h3>
                                    <p>There are no students currently enrolled in this class.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div id="grades-tab" class="tab-content">
                            <?php if (count($grades) > 0): ?>
                                <table class="grades-table">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Assignment</th>
                                            <th>Type</th>
                                            <th>Score</th>
                                            <th>Grade</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grades as $grade): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($grade['last_name'] . ', ' . $grade['first_name']); ?></td>
                                                <td><?php echo htmlspecialchars($grade['assignment_name']); ?></td>
                                                <td><?php echo ucfirst($grade['assignment_type']); ?></td>
                                                <td><?php echo $grade['score']; ?> / <?php echo $grade['max_score']; ?></td>
                                                <td><span class="grade-badge grade-<?php echo strtolower($grade['grade_letter']); ?>"><?php echo $grade['grade_letter']; ?></span></td>
                                                <td><?php echo date('M d, Y', strtotime($grade['graded_at'])); ?></td>
                                                <td class="actions">
                                                    <button class="btn btn-secondary btn-sm" onclick="openEditGradeModal(<?php echo $grade['id']; ?>, '<?php echo htmlspecialchars($grade['assignment_name']); ?>', <?php echo $grade['score']; ?>, <?php echo $grade['max_score']; ?>, '<?php echo htmlspecialchars($grade['comments'] ?? ''); ?>')">Edit</button>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this grade?');">
                                                        <input type="hidden" name="action" value="delete_grade">
                                                        <input type="hidden" name="grade_id" value="<?php echo $grade['id']; ?>">
                                                        <input type="hidden" name="class_id" value="<?php echo $selectedClass['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-state">
                                    <h3>No Grades Yet</h3>
                                    <p>Start by adding grades for your students.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>Select a Class</h3>
                            <p>Please select a class from the dropdown above to view and manage grades.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Grade Modal -->
    <div id="addGradeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('addGradeModal')">&times;</button>
                <h2>Add Grade</h2>
                <p id="addGradeStudentName" style="color: #6b7280; margin-top: 4px;"></p>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_grade">
                <input type="hidden" name="enrollment_id" id="addGradeEnrollmentId">
                <input type="hidden" name="class_id" value="<?php echo $selectedClass['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label for="assignment_name">Assignment Name</label>
                    <input type="text" name="assignment_name" id="assignment_name" required placeholder="e.g., Midterm Exam">
                </div>
                
                <div class="form-group">
                    <label for="assignment_type">Assignment Type</label>
                    <select name="assignment_type" id="assignment_type" required>
                        <option value="homework">Homework</option>
                        <option value="quiz">Quiz</option>
                        <option value="exam">Exam</option>
                        <option value="project">Project</option>
                        <option value="participation">Participation</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="score">Score</label>
                        <input type="number" name="score" id="score" required min="0" step="0.01" placeholder="85">
                    </div>
                    <div class="form-group">
                        <label for="max_score">Max Score</label>
                        <input type="number" name="max_score" id="max_score" required min="1" step="0.01" value="100">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="comments">Comments (Optional)</label>
                    <textarea name="comments" id="comments" rows="3" placeholder="Add any comments..."></textarea>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addGradeModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Grade</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Grade Modal -->
    <div id="editGradeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('editGradeModal')">&times;</button>
                <h2>Edit Grade</h2>
                <p id="editGradeAssignmentName" style="color: #6b7280; margin-top: 4px;"></p>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_grade">
                <input type="hidden" name="grade_id" id="editGradeId">
                <input type="hidden" name="class_id" value="<?php echo $selectedClass['id'] ?? ''; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_score">Score</label>
                        <input type="number" name="score" id="edit_score" required min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="edit_max_score">Max Score</label>
                        <input type="number" name="max_score" id="edit_max_score" required min="1" step="0.01">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_comments">Comments</label>
                    <textarea name="comments" id="edit_comments" rows="3"></textarea>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editGradeModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Grade</button>
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
        
        function openAddGradeModal(enrollmentId, studentName) {
            document.getElementById('addGradeEnrollmentId').value = enrollmentId;
            document.getElementById('addGradeStudentName').textContent = 'Student: ' + studentName;
            document.getElementById('addGradeModal').classList.add('active');
        }
        
        function openEditGradeModal(gradeId, assignmentName, score, maxScore, comments) {
            document.getElementById('editGradeId').value = gradeId;
            document.getElementById('editGradeAssignmentName').textContent = assignmentName;
            document.getElementById('edit_score').value = score;
            document.getElementById('edit_max_score').value = maxScore;
            document.getElementById('edit_comments').value = comments;
            document.getElementById('editGradeModal').classList.add('active');
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
