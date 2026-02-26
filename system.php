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
    $action = $_POST['action'] ?? '';
    
    // Add new subject
    if ($action === 'add_subject') {
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $subjectName = trim($_POST['subject_name'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($subjectCode && $subjectName && $department) {
            try {
                $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, department, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$subjectCode, $subjectName, $department, $description]);
                $message = 'Subject added successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error adding subject: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please fill in all required fields.';
            $messageType = 'error';
        }
    }

    // Edit subject
    if ($action === 'edit_subject') {
        $subjectId = $_POST['subject_id'] ?? '';
        $subjectCode = trim($_POST['subject_code'] ?? '');
        $subjectName = trim($_POST['subject_name'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($subjectId && $subjectCode && $subjectName && $department) {
            try {
                $stmt = $pdo->prepare("UPDATE subjects SET subject_code = ?, subject_name = ?, department = ?, description = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$subjectCode, $subjectName, $department, $description, $isActive, $subjectId]);
                $message = 'Subject updated successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating subject: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    // Delete subject
    if ($action === 'delete_subject') {
        $subjectId = $_POST['subject_id'] ?? '';
        if ($subjectId) {
            try {
                $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
                $stmt->execute([$subjectId]);
                $message = 'Subject deleted successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error deleting subject: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // Create group with subject assignment
    if ($action === 'create_group') {
        $groupName = trim($_POST['group_name'] ?? '');
        $subjectId = $_POST['subject_id'] ?? '';
        $teacherId = $_POST['teacher_id'] ?? '';
        $semester = trim($_POST['semester'] ?? 'Fall');
        $academicYear = intval($_POST['academic_year'] ?? date('Y'));
        $maxStudents = intval($_POST['max_students'] ?? 30);

        if ($groupName && $subjectId) {
            try {
                $stmt = $pdo->prepare("INSERT INTO classes (subject_id, teacher_id, semester, academic_year, schedule, max_students) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$subjectId, $teacherId ?: null, $semester, $academicYear, $groupName, $maxStudents]);
                $message = 'Group created successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error creating group: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please fill in all required fields.';
            $messageType = 'error';
        }
    }

    // Assign subject to existing group
    if ($action === 'assign_subject') {
        $groupId = $_POST['group_id'] ?? '';
        $subjectId = $_POST['subject_id'] ?? '';

        if ($groupId && $subjectId) {
            try {
                $stmt = $pdo->prepare("UPDATE classes SET subject_id = ? WHERE id = ?");
                $stmt->execute([$subjectId, $groupId]);
                $message = 'Subject assigned to group successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error assigning subject: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // Update group teacher
    if ($action === 'update_group_teacher') {
        $groupId = $_POST['group_id'] ?? '';
        $teacherId = $_POST['teacher_id'] ?? '';
        
        if ($groupId) {
            try {
                $stmt = $pdo->prepare("UPDATE classes SET teacher_id = ? WHERE id = ?");
                $stmt->execute([$teacherId ?: null, $groupId]);
                $message = 'Teacher assignment updated successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating teacher assignment: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    // Delete group
    if ($action === 'delete_group') {
        $groupId = $_POST['group_id'] ?? '';
        if ($groupId) {
            try {
                $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
                $stmt->execute([$groupId]);
                $message = 'Group deleted successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error deleting group: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    // Assign subject to class/group
    if ($action === 'assign_subject_to_class') {
        $classId = $_POST['class_id'] ?? '';
        $subjectId = $_POST['subject_id'] ?? '';
        $teacherId = $_POST['teacher_id'] ?? null;
        $semester = trim($_POST['semester'] ?? 'Fall');
        $academicYear = intval($_POST['academic_year'] ?? date('Y'));

        if ($classId && $subjectId) {
            try {
                $stmt = $pdo->prepare("INSERT INTO class_subjects (class_id, subject_id, teacher_id, semester, academic_year) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$classId, $subjectId, $teacherId ?: null, $semester, $academicYear]);
                $message = 'Subject assigned to group successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error assigning subject: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please select both a group and a subject.';
            $messageType = 'error';
        }
    }

    // Remove subject from class/group
    if ($action === 'remove_subject_from_class') {
        $assignmentId = $_POST['assignment_id'] ?? '';
        if ($assignmentId) {
            try {
                $stmt = $pdo->prepare("DELETE FROM class_subjects WHERE id = ?");
                $stmt->execute([$assignmentId]);
                $message = 'Subject removed from group successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error removing subject: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    // Update teacher for class-subject assignment
    if ($action === 'update_assignment_teacher') {
        $assignmentId = $_POST['assignment_id'] ?? '';
        $teacherId = $_POST['teacher_id'] ?? null;

        if ($assignmentId) {
            try {
                $stmt = $pdo->prepare("UPDATE class_subjects SET teacher_id = ? WHERE id = ?");
                $stmt->execute([$teacherId ?: null, $assignmentId]);
                $message = 'Teacher updated successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating teacher: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Get all subjects
$stmt = $pdo->query("SELECT * FROM subjects ORDER BY department, subject_name");
$subjects = $stmt->fetchAll();

// Get all groups/classes with course info (using existing structure)
$stmt = $pdo->query("SELECT c.*, co.course_name, co.course_code, co.department,
                     t.id as teacher_id, u.first_name, u.last_name
                     FROM classes c
                     JOIN courses co ON c.course_id = co.id
                     LEFT JOIN teachers t ON c.teacher_id = t.id
                     LEFT JOIN users u ON t.user_id = u.id
                     ORDER BY co.course_name, c.schedule");
$groups = $stmt->fetchAll();

// Get all class-subject assignments with details
$stmt = $pdo->query("SELECT cs.*, c.schedule as class_name, s.subject_code, s.subject_name, s.department,
                     u.first_name, u.last_name
                     FROM class_subjects cs
                     JOIN classes c ON cs.class_id = c.id
                     JOIN subjects s ON cs.subject_id = s.id
                     LEFT JOIN teachers t ON cs.teacher_id = t.id
                     LEFT JOIN users u ON t.user_id = u.id
                     ORDER BY c.schedule, s.subject_name");
$classSubjects = $stmt->fetchAll();

// Get all teachers for dropdown
$stmt = $pdo->query("SELECT t.id, t.department, u.first_name, u.last_name
                     FROM teachers t
                     JOIN users u ON t.user_id = u.id
                     WHERE u.is_active = 1
                     ORDER BY u.last_name, u.first_name");
$teachers = $stmt->fetchAll();

// Get all departments
$departments = array_unique(array_column($subjects, 'department'));

$navItems = getNavigationItems($userRole);
$pageTitle = 'System Settings';
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
            display: inline-flex;
            align-items: center;
            gap: 6px;
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
        
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 2px solid #e5e7eb; }
        .tab { 
            padding: 12px 20px; 
            background: none; 
            border: none; 
            cursor: pointer; 
            font-size: 14px; 
            font-weight: 500;
            color: #6b7280;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        .tab.active { 
            color: #1e40af; 
            border-bottom-color: #1e40af; 
        }
        .tab:hover { color: #374151; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .section { margin-bottom: 32px; }
        .section h2 { font-size: 18px; color: #1f2937; margin-bottom: 16px; }
        
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
        .form-group textarea { resize: vertical; min-height: 80px; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
        
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
        .badge-red { background: #fee2e2; color: #991b1b; }
        
        .card {
            background: #f9fafb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .card h3 { font-size: 16px; color: #1f2937; margin-bottom: 16px; }
        
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
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #f9fafb; padding: 16px; border-radius: 8px; text-align: center; }
        .stat-card h4 { font-size: 12px; color: #6b7280; margin-bottom: 8px; text-transform: uppercase; }
        .stat-card p { font-size: 24px; font-weight: 700; color: #1e40af; }
        
        .group-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
        }
        .group-card h4 { font-size: 16px; color: #1f2937; margin-bottom: 8px; }
        .group-card .meta { font-size: 13px; color: #6b7280; margin-bottom: 12px; }
        .group-card .actions { display: flex; gap: 8px; }
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
                    
                    <div class="tabs">
                        <button class="tab active" onclick="showTab('subjects')">Subjects</button>
                        <button class="tab" onclick="showTab('groups')">Groups & Assignments</button>
                    </div>
                    
                    <!-- Subjects Tab -->
                    <div id="subjects" class="tab-content active">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <h4>Total Subjects</h4>
                                <p><?php echo count($subjects); ?></p>
                            </div>
                            <div class="stat-card">
                                <h4>Active</h4>
                                <p><?php echo count(array_filter($subjects, function($s) { return $s['is_active']; })); ?></p>
                            </div>
                            <div class="stat-card">
                                <h4>Departments</h4>
                                <p><?php echo count($departments); ?></p>
                            </div>
                        </div>

                        <div class="section">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                <h2>All Subjects</h2>
                                <button class="btn btn-primary" onclick="openModal('addSubjectModal')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    Add Subject
                                </button>
                            </div>

                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $subject): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                            <td><?php echo htmlspecialchars($subject['department']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $subject['is_active'] ? 'badge-green' : 'badge-gray'; ?>">
                                                    <?php echo $subject['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-secondary btn-sm" onclick="editSubject(<?php echo htmlspecialchars(json_encode($subject)); ?>)">Edit</button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this subject?');">
                                                    <input type="hidden" name="action" value="delete_subject">
                                                    <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Groups Tab -->
                    <div id="groups" class="tab-content">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <h4>Total Groups</h4>
                                <p><?php echo count($groups); ?></p>
                            </div>
                            <div class="stat-card">
                                <h4>Subject Assignments</h4>
                                <p><?php echo count($classSubjects); ?></p>
                            </div>
                        </div>

                        <!-- Assign Subject to Group Section -->
                        <div class="section">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                <h2>Assign Subjects to Groups</h2>
                                <button class="btn btn-primary" onclick="openModal('assignSubjectModal')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    Assign Subject
                                </button>
                            </div>

                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Group</th>
                                        <th>Subject</th>
                                        <th>Teacher</th>
                                        <th>Semester</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classSubjects as $assignment): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($assignment['class_name'] ?: 'Group'); ?></strong></td>
                                            <td>
                                                <span class="badge badge-blue"><?php echo htmlspecialchars($assignment['subject_code']); ?></span>
                                                <?php echo htmlspecialchars($assignment['subject_name']); ?>
                                            </td>
                                            <td>
                                                <?php if ($assignment['teacher_id']): ?>
                                                    <?php echo htmlspecialchars($assignment['last_name'] . ', ' . $assignment['first_name']); ?>
                                                <?php else: ?>
                                                    <span style="color: #ef4444;">Not Assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($assignment['semester'] . ' ' . $assignment['academic_year']); ?></td>
                                            <td>
                                                <button class="btn btn-secondary btn-sm" onclick="editAssignment(<?php echo $assignment['id']; ?>, <?php echo $assignment['teacher_id'] ?: 'null'; ?>)">Change Teacher</button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this subject from group?');">
                                                    <input type="hidden" name="action" value="remove_subject_from_class">
                                                    <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                                </form>
                                            </td>
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
    
    <!-- Add Subject Modal -->
    <div id="addSubjectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('addSubjectModal')">&times;</button>
                <h2>Add New Subject</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_subject">

                <div class="form-group">
                    <label>Subject Code *</label>
                    <input type="text" name="subject_code" required placeholder="e.g., MATH101">
                </div>

                <div class="form-group">
                    <label>Subject Name *</label>
                    <input type="text" name="subject_name" required placeholder="e.g., Mathematics">
                </div>

                <div class="form-group">
                    <label>Department *</label>
                    <input type="text" name="department" required placeholder="e.g., Science" list="departments">
                    <datalist id="departments">
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Brief description of the subject..."></textarea>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addSubjectModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Subject</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Subject Modal -->
    <div id="editSubjectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('editSubjectModal')">&times;</button>
                <h2>Edit Subject</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_subject">
                <input type="hidden" name="subject_id" id="editSubjectId">

                <div class="form-group">
                    <label>Subject Code *</label>
                    <input type="text" name="subject_code" id="editSubjectCode" required>
                </div>

                <div class="form-group">
                    <label>Subject Name *</label>
                    <input type="text" name="subject_name" id="editSubjectName" required>
                </div>

                <div class="form-group">
                    <label>Department *</label>
                    <input type="text" name="department" id="editDepartment" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="editDescription"></textarea>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="editIsActive" checked>
                        Active
                    </label>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editSubjectModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Create Group Modal -->
    <div id="createGroupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('createGroupModal')">&times;</button>
                <h2>Create New Group</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_group">
                
                <div class="form-group">
                    <label>Group Name *</label>
                    <input type="text" name="group_name" required placeholder="e.g., Group A, Section 1">
                </div>
                
                <div class="form-group">
                    <label>Subject *</label>
                    <select name="subject_id" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Assign Teacher</label>
                    <select name="teacher_id">
                        <option value="">-- Select Teacher (Optional) --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name'] . ' (' . $teacher['department'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Semester</label>
                        <select name="semester">
                            <option value="Fall">Fall</option>
                            <option value="Spring">Spring</option>
                            <option value="Summer">Summer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Academic Year</label>
                        <input type="number" name="academic_year" value="<?php echo date('Y'); ?>" min="2020" max="2030">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Max Students</label>
                    <input type="number" name="max_students" value="30" min="1" max="100">
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createGroupModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Group</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Group Modal -->
    <div id="editGroupModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('editGroupModal')">&times;</button>
                <h2>Edit Group</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_group_teacher">
                <input type="hidden" name="group_id" id="editGroupId">
                
                <div class="form-group">
                    <label>Group Name</label>
                    <input type="text" id="editGroupName" disabled style="background: #f3f4f6;">
                </div>
                
                <div class="form-group">
                    <label>Change Subject</label>
                    <select name="subject_id" id="editGroupSubject">
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Assign/Change Teacher</label>
                    <select name="teacher_id" id="editGroupTeacher">
                        <option value="">-- No Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name'] . ' (' . $teacher['department'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editGroupModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Subject to Group Modal -->
    <div id="assignSubjectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('assignSubjectModal')">&times;</button>
                <h2>Assign Subject to Group</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="assign_subject_to_class">

                <div class="form-group">
                    <label>Select Group *</label>
                    <select name="class_id" required>
                        <option value="">-- Choose Group --</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo $group['id']; ?>">
                                <?php echo htmlspecialchars($group['schedule'] ?: 'Group ' . $group['id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Select Subject *</label>
                    <select name="subject_id" required>
                        <option value="">-- Choose Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>">
                                <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Assign Teacher (Optional)</label>
                    <select name="teacher_id">
                        <option value="">-- No Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name'] . ' (' . $teacher['department'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Semester</label>
                        <select name="semester">
                            <option value="Fall">Fall</option>
                            <option value="Spring">Spring</option>
                            <option value="Summer">Summer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Academic Year</label>
                        <input type="number" name="academic_year" value="<?php echo date('Y'); ?>" min="2020" max="2030">
                    </div>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('assignSubjectModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Subject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Assignment Teacher Modal -->
    <div id="editAssignmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="modal-close" onclick="closeModal('editAssignmentModal')">&times;</button>
                <h2>Change Teacher</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_assignment_teacher">
                <input type="hidden" name="assignment_id" id="editAssignmentId">

                <div class="form-group">
                    <label>Select Teacher</label>
                    <select name="teacher_id" id="editAssignmentTeacher">
                        <option value="">-- No Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name'] . ' (' . $teacher['department'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editAssignmentModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
        
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function editSubject(subject) {
            document.getElementById('editSubjectId').value = subject.id;
            document.getElementById('editSubjectCode').value = subject.subject_code;
            document.getElementById('editSubjectName').value = subject.subject_name;
            document.getElementById('editDepartment').value = subject.department;
            document.getElementById('editDescription').value = subject.description || '';
            document.getElementById('editIsActive').checked = subject.is_active == 1;
            openModal('editSubjectModal');
        }

        function editGroup(groupId, groupName, subjectId, teacherId) {
            document.getElementById('editGroupId').value = groupId;
            document.getElementById('editGroupName').value = groupName;
            document.getElementById('editGroupSubject').value = subjectId;
            document.getElementById('editGroupTeacher').value = teacherId || '';
            openModal('editGroupModal');
        }

        function editAssignment(assignmentId, teacherId) {
            document.getElementById('editAssignmentId').value = assignmentId;
            document.getElementById('editAssignmentTeacher').value = teacherId || '';
            openModal('editAssignmentModal');
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
