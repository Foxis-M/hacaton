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

// Get teacher's groups/classes - only their own
$stmt = $pdo->prepare("SELECT c.*, co.course_name, co.course_code 
                      FROM classes c 
                      JOIN courses co ON c.course_id = co.id 
                      WHERE c.teacher_id = ? 
                      ORDER BY co.course_name");
$stmt->execute([$teacherId]);
$groups = $stmt->fetchAll();

// Get selected group data
$selectedGroup = null;
$groupAnalytics = [];
$studentPerformance = [];
$gradeTrends = [];
$weakTopics = [];

if (isset($_GET['group_id'])) {
    $groupId = $_GET['group_id'];
    
    // Get group details - verify it belongs to this teacher
    $stmt = $pdo->prepare("SELECT c.*, co.course_name, co.course_code 
                          FROM classes c 
                          JOIN courses co ON c.course_id = co.id 
                          WHERE c.id = ? AND c.teacher_id = ?");
    $stmt->execute([$groupId, $teacherId]);
    $selectedGroup = $stmt->fetch();
    
    // If group doesn't belong to teacher, redirect
    if (!$selectedGroup) {
        header('Location: /academic.php?error=unauthorized_group');
        exit;
    }
    
    // Get student performance data
    $stmt = $pdo->prepare("SELECT s.id, s.student_id, u.first_name, u.last_name,
                          AVG((g.score / g.max_score) * 100) as average,
                          COUNT(g.id) as total_grades,
                          MAX(g.graded_at) as last_grade_date
                          FROM enrollments e
                          JOIN students s ON e.student_id = s.id
                          JOIN users u ON s.user_id = u.id
                          LEFT JOIN grades g ON g.enrollment_id = e.id
                          WHERE e.class_id = ? AND e.status = 'active'
                          GROUP BY s.id
                          ORDER BY average DESC");
    $stmt->execute([$groupId]);
    $studentPerformance = $stmt->fetchAll();
    
    // Get grade trends over time
    $stmt = $pdo->prepare("SELECT DATE(g.graded_at) as date, 
                          AVG((g.score / g.max_score) * 100) as avg_score,
                          COUNT(*) as grade_count
                          FROM grades g
                          JOIN enrollments e ON g.enrollment_id = e.id
                          WHERE e.class_id = ?
                          GROUP BY DATE(g.graded_at)
                          ORDER BY date ASC
                          LIMIT 30");
    $stmt->execute([$groupId]);
    $gradeTrends = $stmt->fetchAll();
    
    // Get assignment type performance (weak topics analysis)
    $stmt = $pdo->prepare("SELECT g.assignment_type,
                          AVG((g.score / g.max_score) * 100) as avg_score,
                          COUNT(*) as count
                          FROM grades g
                          JOIN enrollments e ON g.enrollment_id = e.id
                          WHERE e.class_id = ?
                          GROUP BY g.assignment_type");
    $stmt->execute([$groupId]);
    $weakTopics = $stmt->fetchAll();
}

$navItems = getNavigationItems($userRole);
$pageTitle = 'Academic Process';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - AGKB College</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .page-content { background: white; border-radius: 12px; padding: 24px; min-height: 400px; }
        .page-content h1 { font-size: 24px; color: var(--gray-800); margin-bottom: 24px; }
        
        .group-selector { margin-bottom: 24px; }
        .group-selector select { 
            padding: 10px 16px; 
            border: 1px solid #e5e7eb; 
            border-radius: 8px; 
            font-size: 14px; 
            min-width: 300px;
            cursor: pointer;
        }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: linear-gradient(135deg, #1e40af, #3b82f6); padding: 20px; border-radius: 12px; color: white; }
        .stat-card.secondary { background: #f9fafb; color: #1f2937; }
        .stat-card.secondary h4 { color: #6b7280; }
        .stat-card h4 { font-size: 12px; opacity: 0.9; margin-bottom: 8px; text-transform: uppercase; }
        .stat-card p { font-size: 28px; font-weight: 700; }
        
        .charts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; margin-bottom: 24px; }
        .chart-card { background: #f9fafb; border-radius: 12px; padding: 20px; }
        .chart-card h3 { font-size: 16px; color: #1f2937; margin-bottom: 16px; }
        .chart-container { position: relative; height: 300px; }
        
        .performance-table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        .performance-table th, .performance-table td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #e5e7eb; 
        }
        .performance-table th { 
            background: #f9fafb; 
            font-weight: 600; 
            color: #374151; 
            font-size: 12px;
            text-transform: uppercase;
        }
        .performance-table tr:hover { background: #f9fafb; }
        
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
        
        .weak-topic-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: white;
            border-radius: 8px;
            margin-bottom: 8px;
            border-left: 4px solid #ef4444;
        }
        .weak-topic-item.good { border-left-color: #10b981; }
        .weak-topic-item.warning { border-left-color: #f59e0b; }
        
        .progress-bar {
            width: 100px;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-bar-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .empty-state { text-align: center; padding: 48px 24px; color: #6b7280; }
        .empty-state h3 { font-size: 18px; margin-bottom: 8px; color: #374151; }
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
                    <h1>Academic Process Analysis</h1>
                    
                    <div class="group-selector">
                        <form method="GET" action="">
                            <label for="group_id" style="display: block; margin-bottom: 8px; font-weight: 500;">Select Group:</label>
                            <select name="group_id" id="group_id" onchange="this.form.submit()">
                                <option value="">-- Choose a Group --</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>" <?php echo isset($_GET['group_id']) && $_GET['group_id'] == $group['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(($group['schedule'] ?: 'Group') . ' - ' . $group['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    
                    <?php if ($selectedGroup): ?>
                        <?php
                        // Calculate statistics
                        $totalStudents = count($studentPerformance);
                        $avgGrade = 0;
                        $aboveAverage = 0;
                        $atRisk = 0;
                        
                        if ($totalStudents > 0) {
                            $sum = 0;
                            foreach ($studentPerformance as $student) {
                                $avg = $student['average'] ?? 0;
                                $sum += $avg;
                                if ($avg >= 70) $aboveAverage++;
                                if ($avg < 60) $atRisk++;
                            }
                            $avgGrade = $sum / $totalStudents;
                        }
                        ?>
                        
                        <div class="stats-grid">
                            <div class="stat-card">
                                <h4>Class Average</h4>
                                <p><?php echo round($avgGrade, 1); ?>%</p>
                            </div>
                            <div class="stat-card secondary">
                                <h4>Total Students</h4>
                                <p><?php echo $totalStudents; ?></p>
                            </div>
                            <div class="stat-card secondary">
                                <h4>Performing Well</h4>
                                <p><?php echo $aboveAverage; ?></p>
                            </div>
                            <div class="stat-card secondary">
                                <h4>At Risk</h4>
                                <p><?php echo $atRisk; ?></p>
                            </div>
                        </div>
                        
                        <div class="charts-grid">
                            <!-- Grade Trends Chart -->
                            <div class="chart-card">
                                <h3>Grade Dynamics Over Time</h3>
                                <div class="chart-container">
                                    <canvas id="trendsChart"></canvas>
                                </div>
                            </div>
                            
                            <!-- Assignment Type Performance -->
                            <div class="chart-card">
                                <h3>Performance by Assignment Type</h3>
                                <div class="chart-container">
                                    <canvas id="assignmentChart"></canvas>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Weak Topics Analysis -->
                        <div class="chart-card" style="margin-bottom: 24px;">
                            <h3>Topic Strength Analysis</h3>
                            <?php if (count($weakTopics) > 0): ?>
                                <?php foreach ($weakTopics as $topic): 
                                    $score = $topic['avg_score'];
                                    $statusClass = $score >= 70 ? 'good' : ($score >= 60 ? 'warning' : '');
                                    $color = $score >= 70 ? '#10b981' : ($score >= 60 ? '#f59e0b' : '#ef4444');
                                ?>
                                    <div class="weak-topic-item <?php echo $statusClass; ?>">
                                        <div>
                                            <strong><?php echo ucfirst($topic['assignment_type']); ?></strong>
                                            <div style="font-size: 12px; color: #6b7280;"><?php echo $topic['count']; ?> assignments</div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <span class="grade-badge" style="background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                                <?php echo round($score, 1); ?>%
                                            </span>
                                            <div class="progress-bar">
                                                <div class="progress-bar-fill" style="width: <?php echo $score; ?>%; background: <?php echo $color; ?>"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #6b7280;">No grade data available for analysis.</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Student Performance Table -->
                        <div class="chart-card">
                            <h3>Student Performance Ranking</h3>
                            <?php if (count($studentPerformance) > 0): ?>
                                <table class="performance-table">
                                    <thead>
                                        <tr>
                                            <th>Rank</th>
                                            <th>Student</th>
                                            <th>Average</th>
                                            <th>Grade</th>
                                            <th>Assignments</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $rank = 1;
                                        foreach ($studentPerformance as $student): 
                                            $avg = $student['average'] ?? 0;
                                            $letter = $avg >= 90 ? 'A' : ($avg >= 80 ? 'B' : ($avg >= 70 ? 'C' : ($avg >= 60 ? 'D' : 'F')));
                                            $status = $avg >= 70 ? 'Good' : ($avg >= 60 ? 'Warning' : 'At Risk');
                                            $statusColor = $avg >= 70 ? '#10b981' : ($avg >= 60 ? '#f59e0b' : '#ef4444');
                                        ?>
                                            <tr>
                                                <td>#<?php echo $rank++; ?></td>
                                                <td><?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?></td>
                                                <td><?php echo $avg ? round($avg, 1) . '%' : 'N/A'; ?></td>
                                                <td><span class="grade-badge grade-<?php echo strtolower($letter); ?>"><?php echo $letter; ?></span></td>
                                                <td><?php echo $student['total_grades']; ?></td>
                                                <td><span style="color: <?php echo $statusColor; ?>; font-weight: 500;"><?php echo $status; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="color: #6b7280;">No students enrolled in this group.</p>
                            <?php endif; ?>
                        </div>
                        
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>Select a Group</h3>
                            <p>Choose a group from the dropdown above to view academic performance analysis.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <?php if ($selectedGroup): ?>
    <script>
        // Grade Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($t) { return date('M d', strtotime($t['date'])); }, $gradeTrends)); ?>,
                datasets: [{
                    label: 'Average Score (%)',
                    data: <?php echo json_encode(array_map(function($t) { return round($t['avg_score'], 1); }, $gradeTrends)); ?>,
                    borderColor: '#1e40af',
                    backgroundColor: 'rgba(30, 64, 175, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
        
        // Assignment Type Performance Chart
        const assignmentCtx = document.getElementById('assignmentChart').getContext('2d');
        new Chart(assignmentCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function($t) { return ucfirst($t['assignment_type']); }, $weakTopics)); ?>,
                datasets: [{
                    label: 'Average Score (%)',
                    data: <?php echo json_encode(array_map(function($t) { return round($t['avg_score'], 1); }, $weakTopics)); ?>,
                    backgroundColor: [
                        'rgba(30, 64, 175, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
    
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
    </script>
</body>
</html>
