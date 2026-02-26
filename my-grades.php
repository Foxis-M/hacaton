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

// Only students can view their grades
if ($userRole !== 'student') {
    header('HTTP/1.1 403 Forbidden');
    header('Location: /?error=unauthorized');
    exit;
}

$pdo = getDBConnection();

// Get student ID from current user
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$student = $stmt->fetch();

if (!$student) {
    // Student record not found - redirect to dashboard
    header('Location: /?error=student_record_not_found');
    exit;
}

$studentId = $student['id'];

// Get student's grades with class information
$grades = [];
$enrollments = [];

// Get all enrollments with class details
    $stmt = $pdo->prepare("SELECT e.*, c.semester, c.academic_year, c.schedule as group_name, co.course_name, co.course_code, co.credits,
                          u.first_name as teacher_first, u.last_name as teacher_last
                          FROM enrollments e 
                          JOIN classes c ON e.class_id = c.id 
                          JOIN courses co ON c.course_id = co.id 
                          JOIN teachers t ON c.teacher_id = t.id
                          JOIN users u ON t.user_id = u.id
                          WHERE e.student_id = ? AND e.status = 'active'
                          ORDER BY c.academic_year DESC, c.semester DESC, co.course_name");
    $stmt->execute([$studentId]);
    $enrollments = $stmt->fetchAll();
    
    // Get all grades for this student
    $stmt = $pdo->prepare("SELECT g.*, e.class_id, co.course_name, co.course_code
                          FROM grades g 
                          JOIN enrollments e ON g.enrollment_id = e.id 
                          JOIN classes c ON e.class_id = c.id
                          JOIN courses co ON c.course_id = co.id
                          WHERE e.student_id = ? 
                          ORDER BY g.graded_at DESC");
    $stmt->execute([$studentId]);
    $grades = $stmt->fetchAll();
    
    // Get AI test scores as grades (for classes the student is enrolled in)
    $stmt = $pdo->prepare("SELECT st.*, ct.class_id, ct.title as assignment_title, co.course_name, co.course_code
                          FROM student_tests st
                          JOIN class_topics ct ON st.topic_id = ct.id
                          JOIN classes c ON ct.class_id = c.id
                          JOIN courses co ON c.course_id = co.id
                          JOIN enrollments e ON c.id = e.class_id
                          WHERE st.student_id = ? AND e.student_id = ? AND e.status = 'active' AND st.completed_at IS NOT NULL
                          ORDER BY st.completed_at DESC");
    $stmt->execute([$studentId, $studentId]);
    $aiTestGrades = $stmt->fetchAll();
    
    // Convert AI test grades to regular grade format
    foreach ($aiTestGrades as $aiGrade) {
        $testData = json_decode($aiGrade['content'], true);
        $maxScore = $testData['totalPoints'] ?? ($aiGrade['question_count'] * 10);
        $percentage = $maxScore > 0 ? ($aiGrade['score'] / $maxScore) * 100 : 0;
        
        $grades[] = [
            'id' => 'ai_' . $aiGrade['id'],
            'enrollment_id' => null,
            'class_id' => $aiGrade['class_id'],
            'assignment_title' => 'AI Test: ' . $aiGrade['assignment_title'],
            'score' => $aiGrade['score'],
            'max_score' => $maxScore,
            'grade_letter' => getGradeLetter($percentage),
            'graded_at' => $aiGrade['completed_at'],
            'course_name' => $aiGrade['course_name'],
            'course_code' => $aiGrade['course_code'],
            'is_ai_test' => true
        ];
    }
    
    // Re-sort grades by date
    usort($grades, function($a, $b) {
        return strtotime($b['graded_at']) - strtotime($a['graded_at']);
    });

// Calculate overall GPA
$overallGPA = 0;
$totalCredits = 0;
$weightedPoints = 0;

foreach ($enrollments as $enrollment) {
    $classGrades = array_filter($grades, function($g) use ($enrollment) {
        return $g['class_id'] == $enrollment['class_id'];
    });
    
    if (count($classGrades) > 0) {
        $total = 0;
        foreach ($classGrades as $g) {
            $total += ($g['score'] / $g['max_score']) * 100;
        }
        $avg = $total / count($classGrades);
        $gpa = calculateGPA($avg);
        $weightedPoints += $gpa * $enrollment['credits'];
        $totalCredits += $enrollment['credits'];
    }
}

if ($totalCredits > 0) {
    $overallGPA = round($weightedPoints / $totalCredits, 2);
}

// Get AI Tests history for this student
$aiTests = [];
try {
    $stmt = $pdo->prepare("SELECT st.*, ct.title as topic_title, ct.description as topic_description,
                          co.course_name, co.course_code, c.schedule as group_name
                          FROM student_tests st
                          JOIN class_topics ct ON st.topic_id = ct.id
                          JOIN classes c ON ct.class_id = c.id
                          JOIN courses co ON c.course_id = co.id
                          WHERE st.student_id = ?
                          ORDER BY st.created_at DESC");
    $stmt->execute([$studentId]);
    $aiTests = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
}

function calculateGPA($percentage) {
    if ($percentage >= 90) return 4.0;
    if ($percentage >= 87) return 3.7;
    if ($percentage >= 83) return 3.3;
    if ($percentage >= 80) return 3.0;
    if ($percentage >= 77) return 2.7;
    if ($percentage >= 73) return 2.3;
    if ($percentage >= 70) return 2.0;
    if ($percentage >= 67) return 1.7;
    if ($percentage >= 63) return 1.3;
    if ($percentage >= 60) return 1.0;
    return 0.0;
}

function getGradeLetter($percentage) {
    if ($percentage >= 90) return 'A';
    if ($percentage >= 80) return 'B';
    if ($percentage >= 70) return 'C';
    if ($percentage >= 60) return 'D';
    return 'F';
}

$navItems = getNavigationItems($userRole);
$pageTitle = 'My Grades';
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
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: linear-gradient(135deg, #1e40af, #3b82f6); padding: 20px; border-radius: 12px; color: white; }
        .stat-card h4 { font-size: 12px; opacity: 0.9; margin-bottom: 8px; text-transform: uppercase; }
        .stat-card p { font-size: 32px; font-weight: 700; }
        .stat-card.secondary { background: #f9fafb; color: #1f2937; }
        .stat-card.secondary h4 { color: #6b7280; }
        
        .section { margin-bottom: 32px; }
        .section h2 { font-size: 18px; color: var(--gray-800); margin-bottom: 16px; }
        
        .course-card { 
            background: #f9fafb; 
            border-radius: 12px; 
            padding: 20px; 
            margin-bottom: 16px;
            border: 1px solid #e5e7eb;
        }
        .course-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .course-info h3 { font-size: 16px; color: #1f2937; margin-bottom: 4px; }
        .course-info p { font-size: 13px; color: #6b7280; }
        .course-grade { text-align: right; }
        .course-grade .grade { font-size: 28px; font-weight: 700; }
        .course-grade .gpa { font-size: 13px; color: #6b7280; }
        
        .grades-table { width: 100%; border-collapse: collapse; }
        .grades-table th, .grades-table td { 
            padding: 10px 12px; 
            text-align: left; 
            border-bottom: 1px solid #e5e7eb; 
            font-size: 14px;
        }
        .grades-table th { 
            background: transparent;
            font-weight: 600; 
            color: #6b7280; 
            font-size: 11px;
            text-transform: uppercase;
        }
        .grades-table tr:last-child td { border-bottom: none; }
        
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
        
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; }
        .tab { padding: 8px 16px; border: none; background: transparent; cursor: pointer; font-size: 14px; color: #6b7280; border-radius: 6px; }
        .tab:hover { background: #f3f4f6; }
        .tab.active { background: #1e40af; color: white; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .recent-grades { max-height: 400px; overflow-y: auto; }
        .recent-grade-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .recent-grade-item:last-child { border-bottom: none; }
        .recent-grade-info h4 { font-size: 14px; color: #1f2937; margin-bottom: 2px; }
        .recent-grade-info p { font-size: 12px; color: #6b7280; }
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
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h4>Current GPA</h4>
                            <p><?php echo $overallGPA > 0 ? number_format($overallGPA, 2) : 'N/A'; ?></p>
                        </div>
                        <div class="stat-card secondary">
                            <h4>Total Courses</h4>
                            <p><?php echo count($enrollments); ?></p>
                        </div>
                        <div class="stat-card secondary">
                            <h4>Total Grades</h4>
                            <p><?php echo count($grades); ?></p>
                        </div>
                        <div class="stat-card secondary" style="background: linear-gradient(135deg, #f3e8ff, #e9d5ff);">
                            <h4 style="color: #7c3aed;">AI Tests</h4>
                            <p style="color: #7c3aed;"><?php echo count(array_filter($grades, function($g) { return isset($g['is_ai_test']) && $g['is_ai_test']; })); ?></p>
                        </div>
                    </div>
                    
                    <div class="tabs">
                        <button class="tab active" onclick="showTab('courses')">By Course</button>
                        <button class="tab" onclick="showTab('recent')">Recent Grades</button>
                        <button class="tab" onclick="showTab('ai-tests')">AI Tests History</button>
                    </div>
                    
                    <div id="courses-tab" class="tab-content active">
                        <?php if (count($enrollments) > 0): ?>
                            <?php foreach ($enrollments as $enrollment): 
                                $classGrades = array_filter($grades, function($g) use ($enrollment) {
                                    return $g['class_id'] == $enrollment['class_id'];
                                });
                                $avg = 0;
                                if (count($classGrades) > 0) {
                                    $total = 0;
                                    foreach ($classGrades as $g) {
                                        $total += ($g['score'] / $g['max_score']) * 100;
                                    }
                                    $avg = $total / count($classGrades);
                                }
                            ?>
                                <div class="course-card">
                                    <div class="course-header">
                                        <div class="course-info">
                                            <h3><?php echo htmlspecialchars(($enrollment['group_name'] ?: 'Group') . ' - ' . $enrollment['course_name']); ?></h3>
                                            <p><?php echo htmlspecialchars($enrollment['semester'] . ' ' . $enrollment['academic_year']); ?> • Instructor: <?php echo htmlspecialchars($enrollment['teacher_first'] . ' ' . $enrollment['teacher_last']); ?></p>
                                        </div>
                                        <div class="course-grade">
                                            <?php if (count($classGrades) > 0): ?>
                                                <div class="grade grade-<?php echo strtolower(getGradeLetter($avg)); ?>"><?php echo round($avg, 1); ?>%</div>
                                                <div class="gpa">GPA: <?php echo number_format(calculateGPA($avg), 2); ?></div>
                                            <?php else: ?>
                                                <div style="color: #9ca3af; font-size: 14px;">No grades yet</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (count($classGrades) > 0): ?>
                                        <table class="grades-table">
                                            <thead>
                                                <tr>
                                                    <th>Assignment</th>
                                                    <th>Type</th>
                                                    <th>Score</th>
                                                    <th>Grade</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($classGrades as $grade): ?>
                                                    <tr>
                                                        <td>
                                                            <?php if (isset($grade['is_ai_test']) && $grade['is_ai_test']): ?>
                                                                <span style="color: #8b5cf6;">🤖</span> 
                                                            <?php endif; ?>
                                                            <?php echo htmlspecialchars($grade['assignment_name'] ?? $grade['assignment_title']); ?>
                                                        </td>
                                                        <td><?php echo isset($grade['is_ai_test']) && $grade['is_ai_test'] ? 'AI Test' : ucfirst($grade['assignment_type']); ?></td>
                                                        <td><?php echo $grade['score']; ?> / <?php echo $grade['max_score']; ?></td>
                                                        <td><span class="grade-badge grade-<?php echo strtolower($grade['grade_letter']); ?>"><?php echo $grade['grade_letter']; ?></span></td>
                                                        <td><?php echo date('M d, Y', strtotime($grade['graded_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <h3>No Enrolled Courses</h3>
                                <p>You are not currently enrolled in any courses.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div id="recent-tab" class="tab-content">
                        <?php if (count($grades) > 0): ?>
                            <div class="recent-grades">
                                <?php foreach (array_slice($grades, 0, 20) as $grade): ?>
                                    <div class="recent-grade-item">
                                        <div class="recent-grade-info">
                                            <h4>
                                                <?php if (isset($grade['is_ai_test']) && $grade['is_ai_test']): ?>
                                                    <span style="color: #8b5cf6;">🤖</span> 
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($grade['assignment_name'] ?? $grade['assignment_title']); ?>
                                            </h4>
                                            <p>
                                                <?php echo htmlspecialchars($grade['course_code'] . ' - ' . $grade['course_name']); ?> • 
                                                <?php echo isset($grade['is_ai_test']) && $grade['is_ai_test'] ? 'AI Test' : ucfirst($grade['assignment_type']); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <span class="grade-badge grade-<?php echo strtolower($grade['grade_letter']); ?>">
                                                <?php echo $grade['score']; ?>/<?php echo $grade['max_score']; ?> (<?php echo $grade['grade_letter']; ?>)
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <h3>No Grades Yet</h3>
                                <p>Your grades will appear here once they are posted by your instructors.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div id="ai-tests-tab" class="tab-content">
                        <?php if (count($aiTests) > 0): ?>
                            <div class="section">
                                <h2>AI-Generated Tests History</h2>
                                <?php foreach ($aiTests as $test): 
                                    $testData = json_decode($test['content'], true);
                                    $totalQuestions = count($testData['questions'] ?? []);
                                    $score = $test['score'];
                                    $maxScore = $testData['totalPoints'] ?? ($totalQuestions * 10);
                                    $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100) : 0;
                                ?>
                                    <div class="course-card" style="margin-bottom: 16px;">
                                        <div class="course-header">
                                            <div class="course-info">
                                                <h3><?php echo htmlspecialchars($test['topic_title']); ?></h3>
                                                <p><?php echo htmlspecialchars($test['course_code'] . ' - ' . $test['course_name']); ?> • <?php echo htmlspecialchars($test['group_name']); ?></p>
                                                <p style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                                                    Difficulty: <span class="badge badge-blue"><?php echo ucfirst($test['difficulty']); ?></span>
                                                    Questions: <span class="badge badge-gray"><?php echo $test['question_count']; ?></span>
                                                    Date: <span class="badge badge-gray"><?php echo date('M d, Y', strtotime($test['created_at'])); ?></span>
                                                </p>
                                            </div>
                                            <div class="course-grade">
                                                <?php if ($test['completed_at']): ?>
                                                    <div class="grade grade-<?php echo strtolower(getGradeLetter($percentage)); ?>"><?php echo $percentage; ?>%</div>
                                                    <div class="gpa"><?php echo $score; ?>/<?php echo $maxScore; ?> pts</div>
                                                <?php else: ?>
                                                    <div style="color: #f59e0b; font-size: 14px;">In Progress</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($test['completed_at'] && $testData): ?>
                                            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                                                <h4 style="font-size: 14px; color: #374151; margin-bottom: 12px;">Test Results:</h4>
                                                <div style="display: grid; gap: 8px;">
                                                    <?php 
                                                    // Parse student answers
                                                    $studentAnswers = json_decode($test['student_answers'] ?? '[]', true);
                                                    foreach ($testData['questions'] as $i => $q): 
                                                        $isCorrect = isset($studentAnswers[$i]) && $studentAnswers[$i] === $q['correctAnswer'];
                                                        $studentAnswer = isset($studentAnswers[$i]) ? chr(65 + $studentAnswers[$i]) : 'Not answered';
                                                    ?>
                                                        <div style="padding: 12px; background: <?php echo $isCorrect ? '#ecfdf5' : '#fef2f2'; ?>; border-radius: 8px; border-left: 4px solid <?php echo $isCorrect ? '#10b981' : '#ef4444'; ?>;">
                                                            <div style="font-weight: 500; color: #1f2937; margin-bottom: 8px;">
                                                                <?php echo ($i + 1) . '. ' . htmlspecialchars(substr($q['question'], 0, 100)) . (strlen($q['question']) > 100 ? '...' : ''); ?>
                                                            </div>
                                                            <div style="font-size: 12px; color: #6b7280;">
                                                                Your Answer: <strong style="color: <?php echo $isCorrect ? '#10b981' : '#ef4444'; ?>;"><?php echo $studentAnswer; ?></strong> • 
                                                                Correct: <strong><?php echo chr(65 + $q['correctAnswer']); ?></strong> • 
                                                                Points: <?php echo $q['points']; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                
                                                <?php if ($test['analysis']): ?>
                                                    <div style="margin-top: 20px; background: white; border: 2px solid #8b5cf6; border-radius: 12px; padding: 20px;">
                                                        <h4 style="color: #7c3aed; margin-bottom: 12px; font-size: 16px;">📊 AI Analysis & Recommendations</h4>
                                                        <div style="line-height: 1.7; white-space: pre-wrap; color: #374151;"><?php echo nl2br(htmlspecialchars($test['analysis'])); ?></div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($test['practice_tasks']): ?>
                                                    <div style="margin-top: 16px; background: linear-gradient(135deg, #fef3c7, #fde68a); border: 2px solid #f59e0b; border-radius: 12px; padding: 20px;">
                                                        <h4 style="color: #92400e; margin-bottom: 12px; font-size: 16px;">📝 Practice Tasks for Weak Topics</h4>
                                                        <div style="background: white; border-radius: 8px; padding: 16px; line-height: 1.7; white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($test['practice_tasks'])); ?></div>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div style="margin-top: 16px; text-align: right;">
                                                    <a href="ai-tests.php" class="btn btn-secondary btn-sm">Retake Similar Test</a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <h3>No AI Tests Yet</h3>
                                <p>Generate AI-powered practice tests to see your results here.</p>
                                <a href="ai-tests.php" class="btn btn-primary" style="margin-top: 16px;">Generate AI Test</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
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
    </script>
</body>
</html>
