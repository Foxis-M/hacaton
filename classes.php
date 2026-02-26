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

if ($userRole !== 'teacher') {
    header('HTTP/1.1 403 Forbidden');
    header('Location: /?error=unauthorized');
    exit;
}

$pdo = getDBConnection();

// Get teacher ID
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$teacher = $stmt->fetch();
$teacherId = $teacher ? $teacher['id'] : null;

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Create new topic and assign to groups
    if ($action === 'create_topic') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $selectedGroups = $_POST['groups'] ?? [];
        $dueDate = $_POST['due_date'] ?? null;
        $difficulty = $_POST['difficulty'] ?? 'medium';
        
        if ($title && !empty($selectedGroups)) {
            try {
                foreach ($selectedGroups as $groupId) {
                    $stmt = $pdo->prepare("INSERT INTO class_topics (teacher_id, class_id, title, description, due_date, difficulty, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$teacherId, $groupId, $title, $description, $dueDate, $difficulty]);
                }
                $message = 'Topic created and assigned to selected groups!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error creating topic: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please enter a title and select at least one group.';
            $messageType = 'error';
        }
    }
    
    // Delete topic
    if ($action === 'delete_topic') {
        $topicId = $_POST['topic_id'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM class_topics WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$topicId, $teacherId]);
            $message = 'Topic deleted successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error deleting topic.';
            $messageType = 'error';
        }
    }
}

// Get teacher's groups/classes - from both classes table and group_teachers table
$stmt = $pdo->prepare("SELECT DISTINCT c.*, co.course_name, co.course_code,
                      (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.id AND e.status = 'active') as enrolled_count
                      FROM classes c 
                      JOIN courses co ON c.course_id = co.id 
                      LEFT JOIN group_teachers gt ON c.id = gt.group_id
                      WHERE c.teacher_id = ? OR gt.teacher_id = ?
                      ORDER BY co.course_name, c.schedule");
$stmt->execute([$teacherId, $teacherId]);
$groups = $stmt->fetchAll();

// Get teacher's lesson plans
$stmt = $pdo->prepare("SELECT * FROM lesson_plans WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->execute([$teacherId]);
$lessonPlans = $stmt->fetchAll();

// Get topics created by teacher with group info - group by title to show unique topics
$stmt = $pdo->prepare("SELECT ct.*, c.schedule as group_name, co.course_name, co.course_code
                      FROM class_topics ct
                      JOIN classes c ON ct.class_id = c.id
                      JOIN courses co ON c.course_id = co.id
                      WHERE ct.teacher_id = ?
                      ORDER BY ct.created_at DESC");
$stmt->execute([$teacherId]);
$allTopics = $stmt->fetchAll();

// Group topics by title to combine same topic assigned to multiple groups
$topics = [];
foreach ($allTopics as $topic) {
    $key = $topic['title'] . '_' . $topic['created_at'];
    if (!isset($topics[$key])) {
        $topics[$key] = $topic;
        $topics[$key]['assigned_groups'] = [];
    }
    $topics[$key]['assigned_groups'][] = [
        'id' => $topic['id'],
        'group_name' => $topic['group_name'],
        'course_code' => $topic['course_code']
    ];
}
$topics = array_values($topics);

// Get selected topic or lesson plan details
$selectedItem = null;
$itemType = '';

if (isset($_GET['topic_id'])) {
    $stmt = $pdo->prepare("SELECT ct.*, c.schedule as group_name, co.course_name, co.course_code
                          FROM class_topics ct
                          JOIN classes c ON ct.class_id = c.id
                          JOIN courses co ON c.course_id = co.id
                          WHERE ct.id = ? AND ct.teacher_id = ?");
    $stmt->execute([$_GET['topic_id'], $teacherId]);
    $selectedItem = $stmt->fetch();
    $itemType = 'topic';
} elseif (isset($_GET['plan_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM lesson_plans WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$_GET['plan_id'], $teacherId]);
    $selectedItem = $stmt->fetch();
    $itemType = 'plan';
}

$navItems = getNavigationItems($userRole);
$pageTitle = 'Classes';
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
        
        .card { 
            background: #f9fafb; 
            border-radius: 12px; 
            padding: 20px; 
            margin-bottom: 16px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }
        .card:hover { 
            border-color: #3b82f6; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .card-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            margin-bottom: 12px;
        }
        .card-info h3 { font-size: 16px; color: #1f2937; margin-bottom: 4px; }
        .card-info p { font-size: 13px; color: #6b7280; }
        .card-meta { 
            display: flex; 
            gap: 8px; 
            margin-top: 12px;
            flex-wrap: wrap;
        }
        
        .badge { 
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px; 
            border-radius: 20px; 
            font-size: 11px;
            font-weight: 500;
        }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-gray { background: #f3f4f6; color: #6b7280; }
        .badge-purple { background: #f3e8ff; color: #7c3aed; }
        .badge-orange { background: #ffedd5; color: #9a3412; }
        
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
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        .empty-state { text-align: center; padding: 48px 24px; color: #6b7280; }
        .empty-state h3 { font-size: 18px; margin-bottom: 8px; color: #374151; }
        
        .section { margin-bottom: 32px; }
        .section h2 { font-size: 18px; color: var(--gray-800); margin-bottom: 16px; }
        
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
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
            padding: 12px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-item input[type="checkbox"] {
            width: auto;
        }
        
        .back-link { 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            color: #6b7280; 
            text-decoration: none; 
            margin-bottom: 16px;
            font-size: 14px;
        }
        .back-link:hover { color: #374151; }
        
        .detail-header {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .detail-header h2 { font-size: 22px; margin-bottom: 8px; }
        .detail-header p { opacity: 0.9; }
        
        .content-box {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            white-space: pre-wrap;
            line-height: 1.6;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        @media (max-width: 900px) { .two-column { grid-template-columns: 1fr; } }
        
        .stats-bar {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .stat-pill {
            background: #f9fafb;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
        }
        .stat-pill strong { color: #1e40af; }
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
                    <?php if (!$selectedItem): ?>
                        <h1><?php echo $pageTitle; ?></h1>
                        
                        <?php if ($message): ?>
                            <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>
                        
                        <div class="stats-bar">
                            <div class="stat-pill">
                                <strong><?php echo count($groups); ?></strong> Groups
                            </div>
                            <div class="stat-pill">
                                <strong><?php echo count($lessonPlans); ?></strong> Lesson Plans
                            </div>
                            <div class="stat-pill">
                                <strong><?php echo count($topics); ?></strong> Topics
                            </div>
                        </div>
                        
                        <div class="tabs">
                            <button class="tab active" onclick="showTab('topics')">Topics</button>
                            <button class="tab" onclick="showTab('plans')">Lesson Plans</button>
                            <button class="tab" onclick="showTab('groups')">My Groups</button>
                        </div>
                        
                        <!-- Topics Tab -->
                        <div id="topics" class="tab-content active">
                            <div class="section">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                    <h2>Class Topics</h2>
                                    <button class="btn btn-primary" onclick="openModal('createTopicModal')">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="5" x2="12" y2="19"></line>
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                        Create Topic
                                    </button>
                                </div>
                                
                                <?php if (count($topics) > 0): ?>
                                    <?php foreach ($topics as $topic): ?>
                                        <div class="card">
                                            <div class="card-header">
                                                <div class="card-info">
                                                    <h3><?php echo htmlspecialchars($topic['title']); ?></h3>
                                                    <p>
                                                        Assigned to 
                                                        <?php foreach ($topic['assigned_groups'] as $i => $g): ?>
                                                            <?php echo ($i > 0 ? ', ' : '') . htmlspecialchars($g['group_name'] . ' (' . $g['course_code'] . ')'); ?>
                                                        <?php endforeach; ?>
                                                    </p>
                                                </div>
                                                <div>
                                                    <a href="?topic_id=<?php echo $topic['assigned_groups'][0]['id']; ?>" class="btn btn-secondary btn-sm">View</a>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this topic from all groups?');">
                                                        <input type="hidden" name="action" value="delete_topic">
                                                        <input type="hidden" name="topic_id" value="<?php echo $topic['assigned_groups'][0]['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                            <?php if ($topic['description']): ?>
                                                <p style="font-size: 13px; color: #6b7280; margin-bottom: 8px;"><?php echo htmlspecialchars(substr($topic['description'], 0, 100)) . (strlen($topic['description']) > 100 ? '...' : ''); ?></p>
                                            <?php endif; ?>
                                            <div class="card-meta">
                                                <span class="badge badge-green"><?php echo count($topic['assigned_groups']); ?> group(s)</span>
                                                <?php 
                                                $difficultyColors = ['easy' => 'badge-blue', 'medium' => 'badge-orange', 'hard' => 'badge-red'];
                                                $difficultyLabels = ['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'];
                                                $diffColor = $difficultyColors[$topic['difficulty']] ?? 'badge-gray';
                                                $diffLabel = $difficultyLabels[$topic['difficulty']] ?? ucfirst($topic['difficulty']);
                                                ?>
                                                <span class="badge <?php echo $diffColor; ?>">Test: <?php echo $diffLabel; ?></span>
                                                <?php if ($topic['due_date']): ?>
                                                    <span class="badge badge-gray">Due: <?php echo date('M d, Y', strtotime($topic['due_date'])); ?></span>
                                                <?php endif; ?>
                                                <span class="badge badge-gray"><?php echo date('M d, Y', strtotime($topic['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <h3>No Topics Created</h3>
                                        <p>Create topics and assign them to your groups.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Lesson Plans Tab -->
                        <div id="plans" class="tab-content">
                            <div class="section">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                                    <h2>AI Generated Lesson Plans</h2>
                                    <a href="lesson-planner.php" class="btn btn-primary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                            <path d="M2 17l10 5 10-5"></path>
                                            <path d="M2 12l10 5 10-5"></path>
                                        </svg>
                                        Create New Plan
                                    </a>
                                </div>
                                
                                <?php if (count($lessonPlans) > 0): ?>
                                    <?php foreach ($lessonPlans as $plan): ?>
                                        <div class="card">
                                            <div class="card-header">
                                                <div class="card-info">
                                                    <h3><?php echo htmlspecialchars($plan['topic']); ?></h3>
                                                    <p><?php echo htmlspecialchars($plan['subject'] ?: 'No subject'); ?> • <?php echo $plan['duration']; ?> min</p>
                                                </div>
                                                <a href="?plan_id=<?php echo $plan['id']; ?>" class="btn btn-secondary btn-sm">View Plan</a>
                                            </div>
                                            <div class="card-meta">
                                                <?php if ($plan['grade_level']): ?>
                                                    <span class="badge badge-purple"><?php echo htmlspecialchars($plan['grade_level']); ?></span>
                                                <?php endif; ?>
                                                <span class="badge badge-gray"><?php echo date('M d, Y', strtotime($plan['created_at'])); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <h3>No Lesson Plans</h3>
                                        <p>Use the AI Lesson Planner to create lesson plans.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Groups Tab -->
                        <div id="groups" class="tab-content">
                            <div class="section">
                                <h2>My Groups</h2>
                                
                                <?php if (count($groups) > 0): ?>
                                    <?php foreach ($groups as $group): ?>
                                        <div class="card">
                                            <div class="card-header">
                                                <div class="card-info">
                                                    <h3><?php echo htmlspecialchars(($group['schedule'] ?: 'Group') . ' - ' . $group['course_name']); ?></h3>
                                                    <p><?php echo htmlspecialchars($group['course_code']); ?></p>
                                                </div>
                                            </div>
                                            <div class="card-meta">
                                                <span class="badge badge-blue">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                        <circle cx="9" cy="7" r="4"></circle>
                                                    </svg>
                                                    <?php echo $group['enrolled_count']; ?> Students
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <h3>No Groups Assigned</h3>
                                        <p>You don't have any student groups assigned to you yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    
                    <?php else: ?>
                        <!-- Detail View for Topic or Lesson Plan -->
                        <a href="classes.php" class="back-link">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="19" y1="12" x2="5" y2="12"></line>
                                <polyline points="12 19 5 12 12 5"></polyline>
                            </svg>
                            Back to Classes
                        </a>
                        
                        <?php if ($itemType === 'topic' && $selectedItem): ?>
                            <div class="detail-header">
                                <h2><?php echo htmlspecialchars($selectedItem['title']); ?></h2>
                                <p><?php echo htmlspecialchars($selectedItem['group_name'] . ' • ' . $selectedItem['course_code'] . ' - ' . $selectedItem['course_name']); ?></p>
                            </div>
                            
                            <div class="section">
                                <h3>Description</h3>
                                <div class="content-box" style="background: white; border: 1px solid #e5e7eb;">
                                    <?php echo nl2br(htmlspecialchars($selectedItem['description'] ?: 'No description provided.')); ?>
                                </div>
                            </div>
                            
                            <?php if ($selectedItem['due_date']): ?>
                                <div class="section">
                                    <h3>Due Date</h3>
                                    <p style="font-size: 16px; color: #9a3412;">
                                        <strong><?php echo date('F d, Y', strtotime($selectedItem['due_date'])); ?></strong>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                        <?php elseif ($itemType === 'plan' && $selectedItem): ?>
                            <div class="detail-header">
                                <h2><?php echo htmlspecialchars($selectedItem['topic']); ?></h2>
                                <p><?php echo htmlspecialchars($selectedItem['subject'] ?: 'No subject'); ?> • <?php echo $selectedItem['duration']; ?> minutes</p>
                            </div>
                            
                            <div class="section">
                                <div style="display: flex; gap: 12px; margin-bottom: 16px;">
                                    <?php if ($selectedItem['grade_level']): ?>
                                        <span class="badge badge-purple"><?php echo htmlspecialchars($selectedItem['grade_level']); ?></span>
                                    <?php endif; ?>
                                    <span class="badge badge-gray">Created: <?php echo date('M d, Y', strtotime($selectedItem['created_at'])); ?></span>
                                </div>
                                
                                <h3>Lesson Plan Content</h3>
                                <div class="content-box">
                                    <?php echo nl2br(htmlspecialchars($selectedItem['content'])); ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 12px;">
                                <button class="btn btn-secondary" onclick="window.print()">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                        <rect x="6" y="14" width="12" height="8"></rect>
                                    </svg>
                                    Print
                                </button>
                                <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.querySelector('.content-box').innerText)">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                    Copy
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Create Topic Modal -->
    <div id="createTopicModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
            <div style="margin-bottom: 20px;">
                <button onclick="closeModal('createTopicModal')" style="float: right; font-size: 24px; cursor: pointer; color: #6b7280; background: none; border: none;">&times;</button>
                <h2>Create New Topic</h2>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_topic">
                
                <div class="form-group">
                    <label>Topic Title *</label>
                    <input type="text" name="title" required placeholder="e.g., Chapter 3: Photosynthesis">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Describe the topic, learning objectives, etc."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Assign to Groups * <small style="color: #6b7280; font-weight: normal;">(Select one or more)</small></label>
                    <div class="checkbox-group">
                        <?php foreach ($groups as $group): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="groups[]" value="<?php echo $group['id']; ?>" id="group_<?php echo $group['id']; ?>">
                                <label for="group_<?php echo $group['id']; ?>" style="margin: 0; font-weight: normal; cursor: pointer;">
                                    <?php echo htmlspecialchars(($group['schedule'] ?: 'Group') . ' - ' . $group['course_code']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small style="color: #6b7280; font-size: 12px;">Check multiple boxes to assign this topic to several groups at once</small>
                </div>
                
                <div class="form-group">
                    <label>Due Date (Optional)</label>
                    <input type="date" name="due_date">
                </div>
                
                <div class="form-group">
                    <label>Test Difficulty *</label>
                    <select name="difficulty" required>
                        <option value="easy">Easy - Basic understanding</option>
                        <option value="medium" selected>Medium - Standard level</option>
                        <option value="hard">Hard - Advanced concepts</option>
                    </select>
                    <small style="color: #6b7280; font-size: 12px;">This determines the difficulty of AI-generated tests for this topic</small>
                </div>
                
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createTopicModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Topic</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
        
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
