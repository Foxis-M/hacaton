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
        // Update teacher department/specialization
        if ($_POST['action'] === 'update_teacher') {
            $teacherId = $_POST['teacher_id'] ?? '';
            $department = $_POST['department'] ?? '';
            $specialization = $_POST['specialization'] ?? '';
            
            try {
                $stmt = $pdo->prepare("UPDATE teachers SET department = ?, specialization = ? WHERE id = ?");
                $stmt->execute([$department, $specialization, $teacherId]);
                $message = 'Teacher updated successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating teacher: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
        // Assign multiple teachers to a group with subjects
        if ($_POST['action'] === 'assign_teachers_to_group') {
            $groupId = $_POST['group_id'] ?? '';
            $teacherAssignments = $_POST['teacher_assignments'] ?? [];
            $semester = $_POST['semester'] ?? 'Fall';
            $academicYear = $_POST['academic_year'] ?? date('Y');
            
            if ($groupId && !empty($teacherAssignments)) {
                try {
                    foreach ($teacherAssignments as $assignment) {
                        $teacherId = $assignment['teacher_id'] ?? '';
                        $subjectId = $assignment['subject_id'] ?? null;
                        
                        if ($teacherId) {
                            $stmt = $pdo->prepare("INSERT INTO group_teachers (group_id, teacher_id, subject_id, semester, academic_year) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id)");
                            $stmt->execute([$groupId, $teacherId, $subjectId, $semester, $academicYear]);
                        }
                    }
                    $message = 'Teachers assigned to group successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Error assigning teachers: ' . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = 'Please select a group and at least one teacher.';
                $messageType = 'error';
            }
        }
        
        // Remove teacher from group
        if ($_POST['action'] === 'remove_teacher_from_group') {
            $assignmentId = $_POST['assignment_id'] ?? '';
            
            try {
                $stmt = $pdo->prepare("DELETE FROM group_teachers WHERE id = ?");
                $stmt->execute([$assignmentId]);
                $message = 'Teacher removed from group successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error removing teacher: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
        // Legacy: Assign group to teacher (single teacher)
        if ($_POST['action'] === 'assign_group') {
            $teacherId = $_POST['teacher_id'] ?? '';
            $groupId = $_POST['group_id'] ?? '';
            
            try {
                $stmt = $pdo->prepare("UPDATE classes SET teacher_id = ? WHERE id = ?");
                $stmt->execute([$teacherId, $groupId]);
                $message = 'Group assigned to teacher successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error assigning group: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
        // Legacy: Remove group from teacher
        if ($_POST['action'] === 'remove_group') {
            $groupId = $_POST['group_id'] ?? '';
            
            try {
                $stmt = $pdo->prepare("UPDATE classes SET teacher_id = NULL WHERE id = ?");
                $stmt->execute([$groupId]);
                $message = 'Group unassigned successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error unassigning group: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Get all teachers with their assigned groups
$stmt = $pdo->query("SELECT t.id, t.teacher_id, t.department, t.position, t.specialization, 
                     u.first_name, u.last_name, u.email, u.is_active
                     FROM teachers t 
                     JOIN users u ON t.user_id = u.id 
                     ORDER BY u.last_name, u.first_name");
$teachers = $stmt->fetchAll();

// Get all groups/classes with course info
$stmt = $pdo->query("SELECT c.*, co.course_name, co.course_code,
                     u.first_name as teacher_first, u.last_name as teacher_last
                     FROM classes c 
                     JOIN courses co ON c.course_id = co.id 
                     LEFT JOIN teachers t ON c.teacher_id = t.id
                     LEFT JOIN users u ON t.user_id = u.id
                     ORDER BY co.course_name, c.schedule");
$groups = $stmt->fetchAll();

// Get all subjects for assignment
$stmt = $pdo->query("SELECT id, subject_code, subject_name, department FROM subjects WHERE is_active = 1 ORDER BY department, subject_name");
$subjects = $stmt->fetchAll();

// Get group-teacher assignments with subjects
$stmt = $pdo->query("SELECT gt.*, c.schedule as group_name, co.course_name, co.course_code,
                     u.first_name, u.last_name, s.subject_name, s.subject_code
                     FROM group_teachers gt
                     JOIN classes c ON gt.group_id = c.id
                     JOIN courses co ON c.course_id = co.id
                     JOIN teachers t ON gt.teacher_id = t.id
                     JOIN users u ON t.user_id = u.id
                     LEFT JOIN subjects s ON gt.subject_id = s.id
                     ORDER BY gt.academic_year DESC, gt.semester DESC, c.schedule");
$groupTeacherAssignments = $stmt->fetchAll();

// Get selected teacher details
$selectedTeacher = null;
$teacherGroups = [];
$availableGroups = [];

if (isset($_GET['teacher_id'])) {
    $teacherId = $_GET['teacher_id'];
    
    // Get teacher details
    $stmt = $pdo->prepare("SELECT t.*, u.first_name, u.last_name, u.email
                          FROM teachers t 
                          JOIN users u ON t.user_id = u.id 
                          WHERE t.id = ?");
    $stmt->execute([$teacherId]);
    $selectedTeacher = $stmt->fetch();
    
    // Get groups assigned to this teacher (from group_teachers table)
    $stmt = $pdo->prepare("SELECT gt.*, c.*, co.course_name, co.course_code,
                          (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id AND e.status = 'active') as student_count,
                          s.subject_name, s.subject_code
                          FROM group_teachers gt
                          JOIN classes c ON gt.group_id = c.id
                          JOIN courses co ON c.course_id = co.id
                          LEFT JOIN subjects s ON gt.subject_id = s.id
                          WHERE gt.teacher_id = ?
                          ORDER BY co.course_name");
    $stmt->execute([$teacherId]);
    $teacherGroups = $stmt->fetchAll();
    
    // Get all groups (available to assign)
    $stmt = $pdo->query("SELECT c.*, co.course_name, co.course_code
                         FROM classes c 
                         JOIN courses co ON c.course_id = co.id 
                         ORDER BY co.course_name");
    $availableGroups = $stmt->fetchAll();
}

// Get selected group details with its teachers
$selectedGroup = null;
$groupTeachers = [];

if (isset($_GET['group_id'])) {
    $groupId = $_GET['group_id'];
    
    // Get group details
    $stmt = $pdo->prepare("SELECT c.*, co.course_name, co.course_code
                          FROM classes c 
                          JOIN courses co ON c.course_id = co.id 
                          WHERE c.id = ?");
    $stmt->execute([$groupId]);
    $selectedGroup = $stmt->fetch();
    
    // Get teachers assigned to this group
    $stmt = $pdo->prepare("SELECT gt.*, u.first_name, u.last_name, t.department, s.subject_name, s.subject_code
                          FROM group_teachers gt
                          JOIN teachers t ON gt.teacher_id = t.id
                          JOIN users u ON t.user_id = u.id
                          LEFT JOIN subjects s ON gt.subject_id = s.id
                          WHERE gt.group_id = ?
                          ORDER BY s.subject_name, u.last_name");
    $stmt->execute([$groupId]);
    $groupTeachers = $stmt->fetchAll();
}

$navItems = getNavigationItems($userRole);
$pageTitle = 'Teacher Management';
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
        
        .teacher-card { 
            background: #f9fafb; 
            border-radius: 12px; 
            padding: 16px; 
            margin-bottom: 12px;
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .teacher-info h3 { font-size: 16px; color: #1f2937; margin-bottom: 4px; }
        .teacher-info p { font-size: 13px; color: #6b7280; }
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
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 10px 12px; 
            border: 1px solid #d1d5db; 
            border-radius: 6px; 
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .group-list { max-height: 300px; overflow-y: auto; }
        .group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: white;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid #e5e7eb;
        }
        .group-item:hover { border-color: #3b82f6; }
        
        .section { margin-bottom: 32px; }
        .section h2 { font-size: 18px; color: var(--gray-800); margin-bottom: 16px; }
        
        .empty-state { text-align: center; padding: 48px 24px; color: #6b7280; }
        .empty-state h3 { font-size: 18px; margin-bottom: 8px; color: #374151; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #f9fafb; padding: 16px; border-radius: 8px; text-align: center; }
        .stat-card h4 { font-size: 12px; color: #6b7280; margin-bottom: 8px; text-transform: uppercase; }
        .stat-card p { font-size: 24px; font-weight: 700; color: #1e40af; }
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
                    
                    <?php if (!$selectedTeacher && !$selectedGroup): ?>
                        <!-- Teachers List -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <h4>Total Teachers</h4>
                                <p><?php echo count($teachers); ?></p>
                            </div>
                            <div class="stat-card">
                                <h4>Active</h4>
                                <p><?php echo count(array_filter($teachers, function($t) { return $t['is_active']; })); ?></p>
                            </div>
                            <div class="stat-card">
                                <h4>Total Groups</h4>
                                <p><?php echo count($groups); ?></p>
                            </div>
                        </div>
                        
                        <div class="section">
                            <h2>All Teachers</h2>
                            <?php if (count($teachers) > 0): ?>
                                <?php foreach ($teachers as $teacher): 
                                    // Count groups assigned to this teacher
                                    $assignedGroups = array_filter($groups, function($g) use ($teacher) {
                                        return $g['teacher_id'] == $teacher['id'];
                                    });
                                ?>
                                    <div class="teacher-card">
                                        <div class="teacher-info">
                                            <h3><?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']); ?></h3>
                                            <p>
                                                <span class="badge badge-blue"><?php echo htmlspecialchars($teacher['department']); ?></span>
                                                <span class="badge badge-gray"><?php echo htmlspecialchars($teacher['teacher_id']); ?></span>
                                                <?php if ($teacher['specialization']): ?>
                                                    <span class="badge badge-green"><?php echo htmlspecialchars($teacher['specialization']); ?></span>
                                                <?php endif; ?>
                                                <span class="badge <?php echo $teacher['is_active'] ? 'badge-green' : 'badge-gray'; ?>">
                                                    <?php echo $teacher['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 16px;">
                                            <div style="text-align: right;">
                                                <div style="font-size: 24px; font-weight: 700; color: #1e40af;"><?php echo count($assignedGroups); ?></div>
                                                <div style="font-size: 12px; color: #6b7280;">Groups</div>
                                            </div>
                                            <a href="?teacher_id=<?php echo $teacher['id']; ?>" class="btn btn-primary btn-sm">Manage</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <h3>No Teachers Found</h3>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- All Groups Overview -->
                        <div class="section">
                            <h2>All Groups & Teacher Assignments</h2>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Group</th>
                                        <th>Course</th>
                                        <th>Assigned Teachers</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($groups as $group): 
                                        // Get teachers assigned to this group
                                        $groupTeachersList = array_filter($groupTeacherAssignments, function($gt) use ($group) {
                                            return $gt['group_id'] == $group['id'];
                                        });
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($group['schedule'] ?: 'Group'); ?></td>
                                            <td><?php echo htmlspecialchars($group['course_code'] . ' - ' . $group['course_name']); ?></td>
                                            <td>
                                                <?php if (count($groupTeachersList) > 0): ?>
                                                    <?php foreach ($groupTeachersList as $gt): ?>
                                                        <div style="margin-bottom: 4px;">
                                                            <?php echo htmlspecialchars($gt['last_name'] . ', ' . $gt['first_name']); ?>
                                                            <?php if ($gt['subject_name']): ?>
                                                                <span class="badge badge-blue"><?php echo htmlspecialchars($gt['subject_code']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span style="color: #ef4444;">No teachers assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="?group_id=<?php echo $group['id']; ?>" class="btn btn-primary btn-sm">Manage Teachers</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                    <?php elseif ($selectedTeacher): ?>
                        <!-- Teacher Detail View -->
                        <a href="teachers.php" class="btn btn-secondary btn-sm" style="margin-bottom: 16px;">&larr; Back to Teachers</a>
                        
                        <div style="background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 24px; border-radius: 12px; margin-bottom: 24px;">
                            <h2><?php echo htmlspecialchars($selectedTeacher['first_name'] . ' ' . $selectedTeacher['last_name']); ?></h2>
                            <p><?php echo htmlspecialchars($selectedTeacher['email']); ?> • <?php echo htmlspecialchars($selectedTeacher['department']); ?></p>
                        </div>
                        
                        <div class="section">
                            <h2>Teacher Information</h2>
                            <form method="POST" action="" style="background: #f9fafb; padding: 20px; border-radius: 12px;">
                                <input type="hidden" name="action" value="update_teacher">
                                <input type="hidden" name="teacher_id" value="<?php echo $selectedTeacher['id']; ?>">
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                    <div class="form-group">
                                        <label>Department</label>
                                        <input type="text" name="department" value="<?php echo htmlspecialchars($selectedTeacher['department']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Specialization</label>
                                        <input type="text" name="specialization" value="<?php echo htmlspecialchars($selectedTeacher['specialization'] ?? ''); ?>" placeholder="e.g., Mathematics, Physics">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Update Information</button>
                            </form>
                        </div>
                        
                        <div class="section">
                            <h2>Assigned Groups (<?php echo count($teacherGroups); ?>)</h2>
                            <?php if (count($teacherGroups) > 0): ?>
                                <div class="group-list">
                                    <?php foreach ($teacherGroups as $group): ?>
                                        <div class="group-item">
                                            <div>
                                                <strong><?php echo htmlspecialchars($group['schedule'] ?: 'Group'); ?></strong>
                                                <div style="font-size: 12px; color: #6b7280;">
                                                    <?php echo htmlspecialchars($group['course_code'] . ' - ' . $group['course_name']); ?> • 
                                                    <?php echo $group['student_count']; ?> students
                                                </div>
                                            </div>
                                            <form method="POST" onsubmit="return confirm('Remove this group from teacher?');">
                                                <input type="hidden" name="action" value="remove_group">
                                                <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: #6b7280;">No groups assigned to this teacher yet.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="section">
                            <h2>Assign New Group</h2>
                            <?php if (count($availableGroups) > 0): ?>
                                <form method="POST" action="" style="background: #f9fafb; padding: 20px; border-radius: 12px;">
                                    <input type="hidden" name="action" value="assign_group">
                                    <input type="hidden" name="teacher_id" value="<?php echo $selectedTeacher['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label>Select Group to Assign</label>
                                        <select name="group_id" required>
                                            <option value="">-- Choose a Group --</option>
                                            <?php foreach ($availableGroups as $group): ?>
                                                <option value="<?php echo $group['id']; ?>">
                                                    <?php echo htmlspecialchars(($group['schedule'] ?: 'Group') . ' - ' . $group['course_code'] . ' ' . $group['course_name']); ?>
                                                    <?php echo $group['teacher_id'] ? ' (Currently: ' . $group['teacher_last'] . ')' : ' (Unassigned)'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-success">Assign Group</button>
                                </form>
                            <?php else: ?>
                                <p style="color: #6b7280;">No available groups to assign.</p>
                            <?php endif; ?>
                        </div>
                    
                    <?php elseif ($selectedGroup): ?>
                        <!-- Group Detail View - Manage Multiple Teachers -->
                        <a href="teachers.php" class="btn btn-secondary btn-sm" style="margin-bottom: 16px;">&larr; Back to Teachers</a>
                        
                        <div style="background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 24px; border-radius: 12px; margin-bottom: 24px;">
                            <h2><?php echo htmlspecialchars(($selectedGroup['schedule'] ?: 'Group') . ' - ' . $selectedGroup['course_name']); ?></h2>
                            <p><?php echo htmlspecialchars($selectedGroup['course_code']); ?></p>
                        </div>
                        
                        <div class="section">
                            <h2>Currently Assigned Teachers</h2>
                            <?php if (count($groupTeachers) > 0): ?>
                                <div class="group-list">
                                    <?php foreach ($groupTeachers as $gt): ?>
                                        <div class="group-item">
                                            <div>
                                                <strong><?php echo htmlspecialchars($gt['last_name'] . ', ' . $gt['first_name']); ?></strong>
                                                <div style="font-size: 12px; color: #6b7280;">
                                                    <?php echo htmlspecialchars($gt['department']); ?>
                                                    <?php if ($gt['subject_name']): ?>
                                                        • Subject: <?php echo htmlspecialchars($gt['subject_code'] . ' - ' . $gt['subject_name']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <form method="POST" onsubmit="return confirm('Remove this teacher from group?');">
                                                <input type="hidden" name="action" value="remove_teacher_from_group">
                                                <input type="hidden" name="assignment_id" value="<?php echo $gt['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: #6b7280;">No teachers assigned to this group yet.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="section">
                            <h2>Assign Teachers to This Group</h2>
                            <form method="POST" action="" style="background: #f9fafb; padding: 20px; border-radius: 12px;">
                                <input type="hidden" name="action" value="assign_teachers_to_group">
                                <input type="hidden" name="group_id" value="<?php echo $selectedGroup['id']; ?>">
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                                    <div class="form-group">
                                        <label>Semester</label>
                                        <select name="semester" required>
                                            <option value="Fall">Fall</option>
                                            <option value="Spring">Spring</option>
                                            <option value="Summer">Summer</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Academic Year</label>
                                        <input type="number" name="academic_year" value="<?php echo date('Y'); ?>" min="2020" max="2030" required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Select Teachers & Subjects</label>
                                    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; max-height: 300px; overflow-y: auto;">
                                        <?php foreach ($teachers as $teacher): ?>
                                            <div style="display: flex; align-items: center; gap: 12px; padding: 8px; border-bottom: 1px solid #f3f4f6;">
                                                <input type="checkbox" name="teacher_assignments[<?php echo $teacher['id']; ?>][teacher_id]" value="<?php echo $teacher['id']; ?>" id="teacher_<?php echo $teacher['id']; ?>" style="width: auto;">
                                                <label for="teacher_<?php echo $teacher['id']; ?>" style="margin: 0; flex: 1; font-weight: 500;">
                                                    <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']); ?>
                                                    <span style="font-size: 12px; color: #6b7280;">(<?php echo htmlspecialchars($teacher['department']); ?>)</span>
                                                </label>
                                                <select name="teacher_assignments[<?php echo $teacher['id']; ?>][subject_id]" style="width: 200px;">
                                                    <option value="">-- Select Subject --</option>
                                                    <?php foreach ($subjects as $subject): ?>
                                                        <option value="<?php echo $subject['id']; ?>">
                                                            <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-success">Assign Selected Teachers</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Assign Teacher Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('assignModal')">&times;</button>
                <h2>Assign Teacher to Group</h2>
                <p id="assignGroupName" style="color: #6b7280; margin-top: 4px;"></p>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="assign_group">
                <input type="hidden" name="group_id" id="assignGroupId">
                
                <div class="form-group">
                    <label>Select Teacher</label>
                    <select name="teacher_id" required>
                        <option value="">-- Choose a Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name'] . ' (' . $teacher['department'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
        
        function openAssignModal(groupId, groupName) {
            document.getElementById('assignGroupId').value = groupId;
            document.getElementById('assignGroupName').textContent = 'Group: ' + groupName;
            document.getElementById('assignModal').classList.add('active');
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
