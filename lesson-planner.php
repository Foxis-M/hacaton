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

// Only teachers can access
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

$lessonPlan = '';
$error = '';
$loading = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['topic'])) {
    $topic = trim($_POST['topic'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $gradeLevel = trim($_POST['grade_level'] ?? '');
    $duration = trim($_POST['duration'] ?? '45');
    $objectives = trim($_POST['objectives'] ?? '');
    
    if (empty($topic)) {
        $error = 'Please enter a lesson topic.';
    } else {
        $loading = true;
        
        // Build the prompt for OpenAI
        $prompt = "Create a detailed lesson plan for the following topic:\n\n";
        $prompt .= "Topic: $topic\n";
        if ($subject) $prompt .= "Subject: $subject\n";
        if ($gradeLevel) $prompt .= "Grade Level: $gradeLevel\n";
        $prompt .= "Duration: $duration minutes\n";
        if ($objectives) $prompt .= "Learning Objectives: $objectives\n";
        $prompt .= "\nPlease provide a comprehensive lesson plan that includes:\n";
        $prompt .= "1. Lesson Title\n";
        $prompt .= "2. Learning Objectives\n";
        $prompt .= "3. Materials Needed\n";
        $prompt .= "4. Lesson Structure (Introduction, Main Activity, Conclusion)\n";
        $prompt .= "5. Teaching Methods and Activities\n";
        $prompt .= "6. Assessment Methods\n";
        $prompt .= "7. Homework/Extension Activities\n";
        $prompt .= "8. Differentiation Strategies\n\n";
        $prompt .= "Format the lesson plan in a clear, structured manner suitable for classroom use.";
        
        // OpenAI API call
        $apiKey = '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';
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert educational curriculum designer and lesson planner. Create detailed, practical lesson plans for teachers.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['choices'][0]['message']['content'])) {
                $lessonPlan = $data['choices'][0]['message']['content'];
                
                // Save to database
                $saveSuccess = false;
                try {
                    $stmt = $pdo->prepare("INSERT INTO lesson_plans (teacher_id, topic, subject, grade_level, duration, objectives, content, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$teacherId, $topic, $subject, $gradeLevel, $duration, $objectives, $lessonPlan]);
                    $saveSuccess = true;
                    $savedPlanId = $pdo->lastInsertId();
                } catch (PDOException $e) {
                    // Table might not exist, continue without saving
                    $saveSuccess = false;
                }
            } else {
                $error = 'Failed to generate lesson plan. Please try again.';
            }
        } else {
            $error = 'Error connecting to AI service. Please try again later.';
        }
        
        $loading = false;
    }
}

// Get saved lesson plans for this teacher
$savedPlans = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM lesson_plans WHERE teacher_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$teacherId]);
    $savedPlans = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
}

// Get subjects from database (created by admin)
$subjects = [];
try {
    $stmt = $pdo->query("SELECT id, subject_code, subject_name, department FROM subjects WHERE is_active = 1 ORDER BY department, subject_name");
    $subjects = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist, try courses table as fallback
    try {
        $stmt = $pdo->query("SELECT id, course_code as subject_code, course_name as subject_name, department FROM courses WHERE is_active = 1 ORDER BY department, course_name");
        $subjects = $stmt->fetchAll();
    } catch (PDOException $e2) {
        // Neither table exists
    }
}

$navItems = getNavigationItems($userRole);
$pageTitle = 'AI Lesson Planner';
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
        
        .planner-container { display: grid; grid-template-columns: 350px 1fr; gap: 24px; }
        @media (max-width: 900px) { .planner-container { grid-template-columns: 1fr; } }
        
        .input-panel { background: #f9fafb; padding: 24px; border-radius: 12px; }
        .input-panel h2 { font-size: 18px; color: #1f2937; margin-bottom: 16px; }
        
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
        
        .lesson-plan-content {
            background: white;
            padding: 24px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            white-space: pre-wrap;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
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
        
        .saved-plans { margin-top: 24px; }
        .saved-plans h3 { font-size: 16px; color: #1f2937; margin-bottom: 12px; }
        .plan-item {
            background: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid #e5e7eb;
            cursor: pointer;
        }
        .plan-item:hover { border-color: #3b82f6; }
        .plan-item h4 { font-size: 14px; color: #1f2937; margin-bottom: 4px; }
        .plan-item p { font-size: 12px; color: #6b7280; }
        
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
        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid #a7f3d0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .view-all-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            margin-top: 12px;
        }
        .view-all-link:hover { text-decoration: underline; }
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
                        AI Lesson Planner
                        <span class="ai-badge">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                <path d="M2 17l10 5 10-5"></path>
                                <path d="M2 12l10 5 10-5"></path>
                            </svg>
                            Powered by GPT-4
                        </span>
                    </h1>
                    
                    <div class="planner-container">
                        <div class="input-panel">
                            <h2>Lesson Details</h2>
                            
                            <?php if ($error): ?>
                                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            
                            <?php if (isset($saveSuccess) && $saveSuccess): ?>
                                <div class="success-message">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                    Lesson plan saved successfully!
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="topic">Lesson Topic *</label>
                                    <input type="text" name="topic" id="topic" required 
                                           placeholder="e.g., Photosynthesis in Plants"
                                           value="<?php echo htmlspecialchars($_POST['topic'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="subject">Subject</label>
                                    <?php if (count($subjects) > 0): ?>
                                        <select name="subject" id="subject">
                                            <option value="">-- Select Subject --</option>
                                            <?php 
                                            $currentDept = '';
                                            foreach ($subjects as $subject): 
                                                if ($currentDept !== $subject['department']):
                                                    if ($currentDept !== '') echo '</optgroup>';
                                                    $currentDept = $subject['department'];
                                                    echo '<optgroup label="' . htmlspecialchars($currentDept) . '">';
                                                endif;
                                            ?>
                                                <option value="<?php echo htmlspecialchars($subject['subject_name']); ?>" 
                                                        <?php echo ($_POST['subject'] ?? '') === $subject['subject_name'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                                                </option>
                                            <?php endforeach; 
                                            if ($currentDept !== '') echo '</optgroup>';
                                            ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" name="subject" id="subject" 
                                               placeholder="e.g., Biology (no subjects available in system)"
                                               value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                                        <small style="color: #6b7280; font-size: 12px;">No subjects found. Please ask admin to add subjects in System Settings.</small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label for="grade_level">Grade Level</label>
                                    <select name="grade_level" id="grade_level">
                                        <option value="">-- Select --</option>
                                        <option value="1st Year" <?php echo ($_POST['grade_level'] ?? '') === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                                        <option value="2nd Year" <?php echo ($_POST['grade_level'] ?? '') === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                                        <option value="3rd Year" <?php echo ($_POST['grade_level'] ?? '') === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                                        <option value="4th Year" <?php echo ($_POST['grade_level'] ?? '') === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="duration">Duration (minutes)</label>
                                    <select name="duration" id="duration">
                                        <option value="45" <?php echo ($_POST['duration'] ?? '45') === '45' ? 'selected' : ''; ?>>45 minutes</option>
                                        <option value="60" <?php echo ($_POST['duration'] ?? '') === '60' ? 'selected' : ''; ?>>60 minutes</option>
                                        <option value="90" <?php echo ($_POST['duration'] ?? '') === '90' ? 'selected' : ''; ?>>90 minutes</option>
                                        <option value="120" <?php echo ($_POST['duration'] ?? '') === '120' ? 'selected' : ''; ?>>120 minutes</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="objectives">Learning Objectives (optional)</label>
                                    <textarea name="objectives" id="objectives" 
                                              placeholder="What should students learn?"><?php echo htmlspecialchars($_POST['objectives'] ?? ''); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary" <?php echo $loading ? 'disabled' : ''; ?>>
                                    <?php if ($loading): ?>
                                        <div class="spinner" style="width: 16px; height: 16px; border-width: 2px;"></div>
                                        Generating...
                                    <?php else: ?>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                            <path d="M2 17l10 5 10-5"></path>
                                            <path d="M2 12l10 5 10-5"></path>
                                        </svg>
                                        Generate Lesson Plan
                                    <?php endif; ?>
                                </button>
                            </form>
                            
                            <?php if (count($savedPlans) > 0): ?>
                                <div class="saved-plans">
                                    <h3>Recent Plans</h3>
                                    <?php foreach (array_slice($savedPlans, 0, 5) as $plan): ?>
                                        <div class="plan-item" onclick="location.href='?view_plan=<?php echo $plan['id']; ?>'">
                                            <h4><?php echo htmlspecialchars($plan['topic']); ?></h4>
                                            <p><?php echo date('M d, Y', strtotime($plan['created_at'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                    <a href="my-lesson-plans.php" class="view-all-link">
                                        View all saved plans
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="9 18 15 12 9 6"></polyline>
                                        </svg>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="result-panel">
                            <h2>Generated Lesson Plan</h2>
                            
                            <?php if ($loading): ?>
                                <div class="loading">
                                    <div class="spinner"></div>
                                    <span>AI is creating your lesson plan...</span>
                                </div>
                            <?php elseif ($lessonPlan): ?>
                                <div class="lesson-plan-content"><?php echo nl2br(htmlspecialchars($lessonPlan)); ?></div>
                                <div style="margin-top: 16px; display: flex; gap: 12px;">
                                    <button class="btn btn-secondary" onclick="window.print()">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                            <rect x="6" y="14" width="12" height="8"></rect>
                                        </svg>
                                        Print
                                    </button>
                                    <button class="btn btn-primary" onclick="navigator.clipboard.writeText(document.querySelector('.lesson-plan-content').innerText)">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                        </svg>
                                        Copy
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" style="margin-bottom: 16px;">
                                        <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                        <path d="M2 17l10 5 10-5"></path>
                                        <path d="M2 12l10 5 10-5"></path>
                                    </svg>
                                    <h3>Ready to Create</h3>
                                    <p>Fill in the lesson details and click "Generate Lesson Plan" to create a comprehensive lesson plan with AI.</p>
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
