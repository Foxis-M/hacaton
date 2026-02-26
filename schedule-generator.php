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

// Only admin can access
if ($userRole !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    header('Location: /?error=unauthorized');
    exit;
}

$pdo = getDBConnection();

$schedule = '';
$error = '';
$loading = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id'])) {
    $groupId = $_POST['group_id'] ?? '';
    $weeks = intval($_POST['weeks'] ?? 1);
    $maxClassesPerDay = intval($_POST['max_classes_per_day'] ?? 4);
    $firstClassStart = intval($_POST['first_class_start'] ?? 1);
    
    if (empty($groupId)) {
        $error = 'Please select a group.';
    } else {
        // Get group details
        $stmt = $pdo->prepare("SELECT c.*, co.course_name, co.course_code, co.credits,
                              u.first_name as teacher_first, u.last_name as teacher_last
                              FROM classes c
                              JOIN courses co ON c.course_id = co.id
                              LEFT JOIN teachers t ON c.teacher_id = t.id
                              LEFT JOIN users u ON t.user_id = u.id
                              WHERE c.id = ?");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        
        if (!$group) {
            $error = 'Group not found.';
        } else {
            $loading = true;
            
            // Get all other groups' schedules to avoid conflicts
            $stmt = $pdo->prepare("SELECT c.id, c.schedule as group_name, co.course_name,
                                  cs.day_of_week, cs.start_time, cs.end_time, cs.room
                                  FROM class_schedules cs
                                  JOIN classes c ON cs.class_id = c.id
                                  JOIN courses co ON c.course_id = co.id
                                  WHERE c.id != ? AND cs.day_of_week IS NOT NULL");
            $stmt->execute([$groupId]);
            $existingSchedules = $stmt->fetchAll();
            
            // Get available rooms
            $stmt = $pdo->prepare("SELECT room_number, capacity, building FROM rooms WHERE status = 'active' ORDER BY room_number");
            $stmt->execute();
            $rooms = $stmt->fetchAll();
            
            // Build the prompt for OpenAI
            $prompt = "Create a detailed weekly class schedule for the following student group.\n\n";
            $prompt .= "GROUP INFORMATION:\n";
            $prompt .= "Group: " . ($group['schedule'] ?: 'Group') . "\n";
            $prompt .= "Course: " . $group['course_name'] . " (" . $group['course_code'] . ")\n";
            $prompt .= "Credits: " . $group['credits'] . "\n";
            $prompt .= "Teacher: " . ($group['teacher_first'] ? $group['teacher_first'] . ' ' . $group['teacher_last'] : 'Not assigned') . "\n\n";
            
            $prompt .= "SCHEDULE REQUIREMENTS:\n";
            $prompt .= "- Schedule for: " . $weeks . " week(s)\n";
            $prompt .= "- Maximum classes per day: " . $maxClassesPerDay . "\n";
            $prompt .= "- First class starts at period: " . $firstClassStart . " (1=8:00, 2=9:45, 3=11:30, 4=13:30, 5=15:15)\n";
            $prompt .= "- Classes run Monday through Friday (5-day week)\n";
            $prompt .= "- Each class period is 90 minutes with 15-minute breaks\n\n";
            
            if (count($existingSchedules) > 0) {
                $prompt .= "EXISTING SCHEDULES TO AVOID CONFLICTS:\n";
                foreach ($existingSchedules as $sched) {
                    $prompt .= "- " . $sched['group_name'] . " (" . $sched['course_code'] . "): " . 
                              $sched['day_of_week'] . " " . $sched['start_time'] . "-" . $sched['end_time'] . 
                              " in Room " . $sched['room'] . "\n";
                }
                $prompt .= "\n";
            }
            
            if (count($rooms) > 0) {
                $prompt .= "AVAILABLE ROOMS:\n";
                foreach ($rooms as $room) {
                    $prompt .= "- " . $room['room_number'] . " (" . $room['building'] . ", Capacity: " . $room['capacity'] . ")\n";
                }
                $prompt .= "\n";
            }
            
            $prompt .= "Return a JSON object with this structure:\n";
            $prompt .= "{\n";
            $prompt .= "  \"group_name\": \"Group name\",\n";
            $prompt .= "  \"course\": \"Course name\",\n";
            $prompt .= "  \"weeks\": " . $weeks . ",\n";
            $prompt .= "  \"total_classes\": 12,\n";
            $prompt .= "  \"schedule\": [\n";
            $prompt .= "    {\n";
            $prompt .= "      \"week\": 1,\n";
            $prompt .= "      \"day\": \"Monday\",\n";
            $prompt .= "      \"date\": \"2024-01-15\",\n";
            $prompt .= "      \"classes\": [\n";
            $prompt .= "        {\n";
            $prompt .= "          \"period\": 1,\n";
            $prompt .= "          \"time\": \"08:00-09:30\",\n";
            $prompt .= "          \"subject\": \"Subject name\",\n";
            $prompt .= "          \"room\": \"Room number\",\n";
            $prompt .= "          \"type\": \"Lecture|Lab|Seminar\",\n";
            $prompt .= "          \"notes\": \"Optional notes\"\n";
            $prompt .= "        }\n";
            $prompt .= "      ]\n";
            $prompt .= "    }\n";
            $prompt .= "  ],\n";
            $prompt .= "  \"summary\": {\n";
            $prompt .= "    \"total_hours\": 18,\n";
            $prompt .= "    \"classes_per_week\": 6,\n";
            $prompt .= "    \"room_utilization\": \"Room usage summary\"\n";
            $prompt .= "  }\n";
            $prompt .= "}\n\n";
            $prompt .= "Rules:\n";
            $prompt .= "1. Maximum " . $maxClassesPerDay . " classes per day\n";
            $prompt .= "2. First class can start at period " . $firstClassStart . " or later\n";
            $prompt .= "3. Avoid time conflicts with existing schedules\n";
            $prompt .= "4. Use available rooms efficiently\n";
            $prompt .= "5. Balance workload across the week\n";
            $prompt .= "6. Include breaks between classes\n";
            $prompt .= "7. Return ONLY valid JSON, no markdown formatting\n";
            
            // OpenAI API call
            $apiKey = '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';
            
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert academic schedule planner. Create efficient, conflict-free class schedules.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 4000
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (isset($data['choices'][0]['message']['content'])) {
                    $scheduleContent = $data['choices'][0]['message']['content'];
                    
                    // Clean up JSON
                    $scheduleContent = preg_replace('/^```json\s*/', '', $scheduleContent);
                    $scheduleContent = preg_replace('/```\s*$/', '', $scheduleContent);
                    $scheduleContent = trim($scheduleContent);
                    
                    $scheduleData = json_decode($scheduleContent, true);
                    if ($scheduleData && isset($scheduleData['schedule'])) {
                        $schedule = $scheduleContent;
                        
                        // Save to database
                        try {
                            $stmt = $pdo->prepare("INSERT INTO generated_schedules (group_id, admin_id, schedule_data, weeks, max_classes_per_day, first_class_start, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                            $stmt->execute([$groupId, $currentUser['id'], $schedule, $weeks, $maxClassesPerDay, $firstClassStart]);
                        } catch (PDOException $e) {
                            // Table might not exist, continue
                        }
                    } else {
                        $error = 'Failed to parse schedule data. Please try again.';
                    }
                } else {
                    $error = 'Failed to generate schedule. Please try again.';
                }
            } else {
                $error = 'Error connecting to AI service. Please try again later.';
            }
            
            $loading = false;
        }
    }
}

// Get all groups for selection
$stmt = $pdo->prepare("SELECT c.*, co.course_name, co.course_code, co.credits,
                      u.first_name as teacher_first, u.last_name as teacher_last
                      FROM classes c
                      JOIN courses co ON c.course_id = co.id
                      LEFT JOIN teachers t ON c.teacher_id = t.id
                      LEFT JOIN users u ON t.user_id = u.id
                      ORDER BY co.course_name, c.schedule");
$stmt->execute();
$groups = $stmt->fetchAll();

// Get saved schedules
$savedSchedules = [];
try {
    $stmt = $pdo->prepare("SELECT gs.*, c.schedule as group_name, co.course_name, co.course_code
                          FROM generated_schedules gs
                          JOIN classes c ON gs.group_id = c.id
                          JOIN courses co ON c.course_id = co.id
                          ORDER BY gs.created_at DESC LIMIT 10");
    $stmt->execute();
    $savedSchedules = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
}

$navItems = getNavigationItems($userRole);
$pageTitle = 'AI Schedule Generator';
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
        
        .schedule-container { display: grid; grid-template-columns: 350px 1fr; gap: 24px; }
        @media (max-width: 900px) { .schedule-container { grid-template-columns: 1fr; } }
        
        .input-panel { background: #f9fafb; padding: 24px; border-radius: 12px; }
        .input-panel h2 { font-size: 18px; color: #1f2937; margin-bottom: 16px; }
        
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
        
        .btn { 
            padding: 12px 24px; 
            border: none; 
            border-radius: 6px; 
            font-size: 14px; 
            cursor: pointer; 
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        .btn-primary { 
            background: linear-gradient(135deg, #1e40af, #3b82f6); 
            color: white; 
            width: 100%;
            justify-content: center;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4); }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        
        .result-panel { background: #f9fafb; padding: 24px; border-radius: 12px; }
        .result-panel h2 { font-size: 18px; color: #1f2937; margin-bottom: 16px; }
        
        .schedule-content {
            background: white;
            padding: 24px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .empty-state { 
            text-align: center; 
            padding: 48px 24px; 
            color: #6b7280; 
            background: white;
            border-radius: 8px;
            border: 2px dashed #e5e7eb;
        }
        .empty-state h3 { font-size: 18px; margin-bottom: 8px; color: #374151; }
        
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid #fecaca;
        }
        
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 48px;
            color: #6b7280;
        }
        .spinner {
            width: 24px;
            height: 24px;
            border: 3px solid #e5e7eb;
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .schedule-day {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .schedule-day-header {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            color: white;
            padding: 12px 16px;
            font-weight: 600;
        }
        .schedule-class {
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .schedule-class:last-child { border-bottom: none; }
        .schedule-time {
            font-weight: 600;
            color: #1e40af;
            min-width: 100px;
        }
        .schedule-info {
            flex: 1;
            padding: 0 16px;
        }
        .schedule-room {
            background: #ecfdf5;
            color: #065f46;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .schedule-type {
            background: #eff6ff;
            color: #1e40af;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 8px;
        }
        
        .saved-schedules { margin-top: 24px; }
        .saved-schedules h3 { font-size: 16px; color: #1f2937; margin-bottom: 12px; }
        .schedule-item {
            background: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid #e5e7eb;
        }
        .schedule-item:hover { border-color: #3b82f6; }
        
        .ai-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #8b5cf6, #a78bfa);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: 13px;
            color: #1e40af;
        }
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
                    <h1>
                        AI Schedule Generator
                        <span class="ai-badge">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                <path d="M2 17l10 5 10-5"></path>
                                <path d="M2 12l10 5 10-5"></path>
                            </svg>
                            Powered by GPT-4
                        </span>
                    </h1>
                    
                    <div class="schedule-container">
                        <div class="input-panel">
                            <h2>Generate Schedule</h2>
                            
                            <?php if ($error): ?>
                                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            
                            <div class="info-box">
                                <strong>Class Periods:</strong><br>
                                1: 08:00-09:30 | 2: 09:45-11:15 | 3: 11:30-13:00<br>
                                4: 13:30-15:00 | 5: 15:15-16:45
                            </div>
                            
                            <?php if (count($groups) > 0): ?>
                                <form method="POST" action="">
                                    <div class="form-group">
                                        <label for="group_id">Select Group *</label>
                                        <select name="group_id" id="group_id" required>
                                            <option value="">-- Choose a Group --</option>
                                            <?php foreach ($groups as $group): ?>
                                                <option value="<?php echo $group['id']; ?>" <?php echo ($_POST['group_id'] ?? '') == $group['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars(($group['schedule'] ?: 'Group') . ' - ' . $group['course_code'] . ' (' . $group['credits'] . ' cr)'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="weeks">Number of Weeks</label>
                                        <select name="weeks" id="weeks">
                                            <option value="1" <?php echo ($_POST['weeks'] ?? '1') === '1' ? 'selected' : ''; ?>>1 week</option>
                                            <option value="2" <?php echo ($_POST['weeks'] ?? '') === '2' ? 'selected' : ''; ?>>2 weeks</option>
                                            <option value="4" <?php echo ($_POST['weeks'] ?? '') === '4' ? 'selected' : ''; ?>>4 weeks</option>
                                            <option value="8" <?php echo ($_POST['weeks'] ?? '') === '8' ? 'selected' : ''; ?>>8 weeks</option>
                                            <option value="16" <?php echo ($_POST['weeks'] ?? '') === '16' ? 'selected' : ''; ?>>16 weeks (semester)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="max_classes_per_day">Max Classes Per Day</label>
                                        <select name="max_classes_per_day" id="max_classes_per_day">
                                            <option value="2">2 classes</option>
                                            <option value="3">3 classes</option>
                                            <option value="4" selected>4 classes</option>
                                            <option value="5">5 classes</option>
                                            <option value="6">6 classes</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="first_class_start">First Class Starts At</label>
                                        <select name="first_class_start" id="first_class_start">
                                            <option value="1" selected>Period 1 (08:00)</option>
                                            <option value="2">Period 2 (09:45)</option>
                                            <option value="3">Period 3 (11:30)</option>
                                            <option value="4">Period 4 (13:30)</option>
                                            <option value="5">Period 5 (15:15)</option>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" <?php echo $loading ? 'disabled' : ''; ?>>
                                        <?php if ($loading): ?>
                                            <div class="spinner" style="width: 16px; height: 16px; border-width: 2px;"></div>
                                            Generating Schedule...
                                        <?php else: ?>
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                                <line x1="3" y1="10" x2="21" y2="10"></line>
                                            </svg>
                                            Generate Schedule
                                        <?php endif; ?>
                                    </button>
                                </form>
                                
                                <?php if (count($savedSchedules) > 0): ?>
                                    <div class="saved-schedules">
                                        <h3>Recent Schedules</h3>
                                        <?php foreach (array_slice($savedSchedules, 0, 5) as $sched): ?>
                                            <div class="schedule-item">
                                                <h4><?php echo htmlspecialchars($sched['group_name'] . ' - ' . $sched['course_code']); ?></h4>
                                                <p style="font-size: 12px; color: #6b7280;">
                                                    <?php echo $sched['weeks']; ?> week(s) • 
                                                    Max <?php echo $sched['max_classes_per_day']; ?>/day • 
                                                    <?php echo date('M d, Y', strtotime($sched['created_at'])); ?>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="empty-state" style="padding: 24px;">
                                    <h3>No Groups Available</h3>
                                    <p>Create groups in System Settings first.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="result-panel">
                            <h2>Generated Schedule</h2>
                            
                            <?php if ($loading): ?>
                                <div class="loading">
                                    <div class="spinner"></div>
                                    <span>AI is creating the schedule...</span>
                                </div>
                            <?php elseif ($schedule): 
                                $scheduleData = json_decode($schedule, true);
                            ?>
                                <div class="schedule-content">
                                    <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #e5e7eb;">
                                        <h3 style="font-size: 20px; color: #1f2937; margin-bottom: 8px;">
                                            <?php echo htmlspecialchars($scheduleData['group_name'] ?? 'Schedule'); ?>
                                        </h3>
                                        <p style="color: #6b7280;"><?php echo htmlspecialchars($scheduleData['course'] ?? ''); ?></p>
                                        <div style="margin-top: 12px; display: flex; gap: 12px;">
                                            <span class="badge badge-blue"><?php echo $scheduleData['weeks']; ?> week(s)</span>
                                            <span class="badge badge-green"><?php echo $scheduleData['total_classes']; ?> classes</span>
                                            <?php if (isset($scheduleData['summary']['total_hours'])): ?>
                                                <span class="badge badge-orange"><?php echo $scheduleData['summary']['total_hours']; ?> hours</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php foreach ($scheduleData['schedule'] as $day): ?>
                                        <div class="schedule-day">
                                            <div class="schedule-day-header">
                                                <?php echo htmlspecialchars($day['day'] . ', ' . $day['date']); ?> 
                                                <span style="opacity: 0.8; font-weight: normal;">(Week <?php echo $day['week']; ?>)</span>
                                            </div>
                                            <?php if (!empty($day['classes'])): ?>
                                                <?php foreach ($day['classes'] as $class): ?>
                                                    <div class="schedule-class">
                                                        <div class="schedule-time"><?php echo htmlspecialchars($class['time']); ?></div>
                                                        <div class="schedule-info">
                                                            <div style="font-weight: 500;"><?php echo htmlspecialchars($class['subject']); ?></div>
                                                            <?php if (isset($class['notes']) && $class['notes']): ?>
                                                                <div style="font-size: 12px; color: #6b7280; margin-top: 2px;"><?php echo htmlspecialchars($class['notes']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <span class="schedule-room"><?php echo htmlspecialchars($class['room']); ?></span>
                                                            <span class="schedule-type"><?php echo htmlspecialchars($class['type']); ?></span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="schedule-class" style="color: #9ca3af; font-style: italic;">
                                                    No classes scheduled
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (isset($scheduleData['summary'])): ?>
                                        <div style="margin-top: 20px; padding: 16px; background: #f9fafb; border-radius: 8px;">
                                            <h4 style="font-size: 14px; color: #374151; margin-bottom: 8px;">Summary</h4>
                                            <p style="font-size: 13px; color: #6b7280;">
                                                <?php echo htmlspecialchars($scheduleData['summary']['room_utilization'] ?? ''); ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div style="margin-top: 16px; display: flex; gap: 12px;">
                                        <button class="btn btn-secondary" onclick="window.print()">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                                <rect x="6" y="14" width="12" height="8"></rect>
                                            </svg>
                                            Print Schedule
                                        </button>
                                        <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.querySelector('.schedule-content').innerText)">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                            </svg>
                                            Copy
                                        </button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" style="margin-bottom: 16px;">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                    </svg>
                                    <h3>Ready to Generate</h3>
                                    <p>Select a group and configure schedule options, then click "Generate Schedule" to create an AI-powered schedule.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
    </script>
</body>
</html>
