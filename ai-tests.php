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

// Only students can access
if ($userRole !== 'student') {
    header('HTTP/1.1 403 Forbidden');
    header('Location: /?error=unauthorized');
    exit;
}

$pdo = getDBConnection();

// Get student ID
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$student = $stmt->fetch();
$studentId = $student ? $student['id'] : null;

$test = '';
$error = '';
$loading = false;
$testId = null;
$gradingResult = null;

// Handle test generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_test') {
    $topicId = $_POST['topic_id'] ?? '';
    $questionCount = intval($_POST['question_count'] ?? 10);
    
    if (empty($topicId)) {
        $error = 'Please select a topic.';
    } else {
        // Get topic details including difficulty set by teacher
        $stmt = $pdo->prepare("SELECT ct.*, c.schedule as group_name, co.course_name
                              FROM class_topics ct
                              JOIN classes c ON ct.class_id = c.id
                              JOIN courses co ON c.course_id = co.id
                              JOIN enrollments e ON c.id = e.class_id
                              WHERE ct.id = ? AND e.student_id = ? AND e.status = 'active'");
        $stmt->execute([$topicId, $studentId]);
        $topic = $stmt->fetch();
        
        if (!$topic) {
            $error = 'Topic not found or you are not enrolled in this group.';
        } else {
            $loading = true;
            
            // Use difficulty set by teacher
            $difficulty = $topic['difficulty'] ?? 'medium';
            
            // Build the prompt for OpenAI - request JSON format for interactive test
            $prompt = "Create a multiple choice test for the following topic. Return ONLY a valid JSON object.\n\n";
            $prompt .= "Topic: {$topic['title']}\n";
            $prompt .= "Subject/Course: {$topic['course_name']}\n";
            if ($topic['description']) {
                $prompt .= "Description: {$topic['description']}\n";
            }
            $prompt .= "Difficulty Level: $difficulty (set by teacher)\n";
            $prompt .= "Number of Questions: $questionCount\n\n";
            $prompt .= "Return a JSON object with this exact structure:\n";
            $prompt .= "{\n";
            $prompt .= "  \"title\": \"Test title\",\n";
            $prompt .= "  \"instructions\": \"Test instructions\",\n";
            $prompt .= "  \"totalPoints\": 100,\n";
            $prompt .= "  \"estimatedTime\": \"20 minutes\",\n";
            $prompt .= "  \"questions\": [\n";
            $prompt .= "    {\n";
            $prompt .= "      \"id\": 1,\n";
            $prompt .= "      \"type\": \"multiple_choice\",\n";
            $prompt .= "      \"question\": \"Question text\",\n";
            $prompt .= "      \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],\n";
            $prompt .= "      \"correctAnswer\": 0,\n";
            $prompt .= "      \"points\": 10\n";
            $prompt .= "    }\n";
            $prompt .= "  ]\n";
            $prompt .= "}\n\n";
            $prompt .= "Rules:\n";
            $prompt .= "- Generate exactly $questionCount questions\n";
            $prompt .= "- All questions must be multiple_choice type\n";
            $prompt .= "- correctAnswer is the INDEX (0-3) of the correct option\n";
            $prompt .= "- Each question should have exactly 4 options\n";
            $prompt .= "- Make questions appropriate for $difficulty difficulty\n";
            $prompt .= "- Return ONLY the JSON, no markdown formatting, no code blocks";
            
            // OpenAI API call
            $apiKey = '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';
            
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert educational assessment designer. Create tests in valid JSON format only.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 3000
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
                    $testContent = $data['choices'][0]['message']['content'];
                    
                    // Clean up JSON - remove markdown code blocks if present
                    $testContent = preg_replace('/^```json\s*/', '', $testContent);
                    $testContent = preg_replace('/```\s*$/', '', $testContent);
                    $testContent = trim($testContent);
                    
                    // Parse JSON
                    $testData = json_decode($testContent, true);
                    if ($testData && isset($testData['questions'])) {
                        $test = json_encode($testData);
                        
                        // Save to database
                        try {
                            $stmt = $pdo->prepare("INSERT INTO student_tests (student_id, topic_id, difficulty, question_count, content, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                            $stmt->execute([$studentId, $topicId, $difficulty, $questionCount, $test]);
                            $testId = $pdo->lastInsertId();
                        } catch (PDOException $e) {
                            // Table might not exist, continue without saving
                        }
                    } else {
                        $error = 'Failed to parse test data. Please try again.';
                    }
                } else {
                    $error = 'Failed to generate test. Please try again.';
                }
            } else {
                $error = 'Error connecting to AI service. Please try again later.';
            }
            
            $loading = false;
        }
    }
}

// Handle test submission and grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_test') {
    $testId = $_POST['test_id'] ?? '';
    $studentAnswers = $_POST['answers'] ?? '';
    
    if ($testId && $studentAnswers) {
        // Get the test content
        $stmt = $pdo->prepare("SELECT * FROM student_tests WHERE id = ? AND student_id = ?");
        $stmt->execute([$testId, $studentId]);
        $testData = $stmt->fetch();
        
        if ($testData) {
            $loading = true;
            
            // Build grading prompt
            $prompt = "You are an AI grader. Grade the following student test submission.\n\n";
            $prompt .= "=== ORIGINAL TEST ===\n";
            $prompt .= $testData['content'] . "\n\n";
            $prompt .= "=== STUDENT ANSWERS ===\n";
            $prompt .= $studentAnswers . "\n\n";
            $prompt .= "Please grade this test and provide:\n";
            $prompt .= "1. Score for each question (points earned / total points)\n";
            $prompt .= "2. Brief feedback for each answer (what was correct/incorrect)\n";
            $prompt .= "3. Total score (sum of all points earned)\n";
            $prompt .= "4. Maximum possible score\n";
            $prompt .= "5. Percentage score\n";
            $prompt .= "6. Letter grade (A, B, C, D, F)\n";
            $prompt .= "7. Overall feedback and suggestions for improvement\n\n";
            $prompt .= "Format your response clearly with sections.";
            
            // OpenAI API call for grading
            $apiKey = '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';
            
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert educational grader. Grade student work fairly and provide constructive feedback.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.5,
                'max_tokens' => 2500
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
                    $gradingResult = $data['choices'][0]['message']['content'];
                    
                    // Try to extract score from grading result
                    $score = null;
                    if (preg_match('/(?:Total score|Score):\s*(\d+)/i', $gradingResult, $matches)) {
                        $score = intval($matches[1]);
                    }
                    
                    // Save grading result to database
                    try {
                        $stmt = $pdo->prepare("UPDATE student_tests SET score = ?, completed_at = NOW() WHERE id = ?");
                        $stmt->execute([$score, $testId]);
                    } catch (PDOException $e) {
                        // Continue even if update fails
                    }
                } else {
                    $error = 'Failed to grade test. Please try again.';
                }
            } else {
                $error = 'Error connecting to AI grading service. Please try again later.';
            }
            
            $loading = false;
        }
    }
}

// Handle saving score from interactive test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_score') {
    $testId = $_POST['test_id'] ?? '';
    $score = $_POST['score'] ?? '';
    $answers = $_POST['answers'] ?? '';
    
    if ($testId && $score !== '') {
        try {
            $stmt = $pdo->prepare("UPDATE student_tests SET score = ?, student_answers = ?, completed_at = NOW() WHERE id = ? AND student_id = ?");
            $stmt->execute([$score, $answers, $testId, $studentId]);
        } catch (PDOException $e) {
            // Continue even if update fails
        }
    }
    // Return empty response for AJAX
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Handle saving analysis and practice tasks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_analysis') {
    $testId = $_POST['test_id'] ?? '';
    $analysis = $_POST['analysis'] ?? '';
    $practiceTasks = $_POST['practice_tasks'] ?? '';
    
    if ($testId) {
        try {
            $stmt = $pdo->prepare("UPDATE student_tests SET analysis = ?, practice_tasks = ? WHERE id = ? AND student_id = ?");
            $stmt->execute([$analysis, $practiceTasks, $testId, $studentId]);
        } catch (PDOException $e) {
            // Continue even if update fails
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Handle AI analysis request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'analyze_test') {
    $analysisData = json_decode($_POST['analysis_data'] ?? '{}', true);
    
    if (!empty($analysisData)) {
        // Build prompt for AI analysis
        $prompt = "Analyze this student's test performance and provide detailed feedback.\n\n";
        $prompt .= "Test Topic: " . ($analysisData['topic'] ?? 'General') . "\n";
        $prompt .= "Total Questions: " . ($analysisData['totalQuestions'] ?? 0) . "\n";
        $prompt .= "Correct Answers: " . ($analysisData['correctAnswers'] ?? 0) . "\n";
        $prompt .= "Wrong Answers: " . ($analysisData['wrongAnswers'] ?? 0) . "\n";
        $prompt .= "Score Percentage: " . ($analysisData['percentage'] ?? 0) . "%\n\n";
        
        if (!empty($analysisData['wrongQuestions'])) {
            $prompt .= "Questions Answered Incorrectly:\n";
            foreach ($analysisData['wrongQuestions'] as $i => $wq) {
                $prompt .= ($i + 1) . ". Question: " . $wq['question'] . "\n";
                $userAnswer = $wq['userAnswer'] !== null ? chr(65 + $wq['userAnswer']) : 'Not answered';
                $correctAnswer = chr(65 + $wq['correctAnswer']);
                $prompt .= "   Student's Answer: " . $userAnswer . "\n";
                $prompt .= "   Correct Answer: " . $correctAnswer . "\n\n";
            }
        }
        
        $prompt .= "\nPlease provide a comprehensive analysis including:\n\n";
        $prompt .= "1. OVERALL PERFORMANCE SUMMARY\n";
        $prompt .= "   - Brief assessment of the student's performance\n";
        $prompt .= "   - What the score indicates about their understanding\n\n";
        $prompt .= "2. ERROR ANALYSIS\n";
        $prompt .= "   - Patterns in the mistakes made\n";
        $prompt .= "   - Common misconceptions or gaps in knowledge\n";
        $prompt .= "   - Why the wrong answers were chosen\n\n";
        $prompt .= "3. TOPICS NEEDING REVIEW\n";
        $prompt .= "   - Specific concepts that need more study\n";
        $prompt .= "   - Priority order for review (most important first)\n\n";
        $prompt .= "4. STUDY RECOMMENDATIONS\n";
        $prompt .= "   - Specific resources or methods to improve\n";
        $prompt .= "   - Practice strategies\n";
        $prompt .= "   - Tips for avoiding similar mistakes\n\n";
        $prompt .= "5. ENCOURAGEMENT\n";
        $prompt .= "   - Positive reinforcement\n";
        $prompt .= "   - Motivational closing statement\n\n";
        $prompt .= "Format your response in clear sections with headers. Be encouraging but honest about areas needing improvement.";
        
        // OpenAI API call for analysis
        $apiKey = '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';
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert educational analyst and tutor. Provide constructive, detailed feedback on student test performance.'],
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
        
        header('Content-Type: application/json');
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['choices'][0]['message']['content'])) {
                $analysis = $data['choices'][0]['message']['content'];
                
                // Generate additional practice tasks for weak topics
                $practiceTasks = null;
                if (!empty($analysisData['wrongQuestions'])) {
                    $weakTopicsPrompt = "Based on the following wrong answers from a student test, create 3-5 additional practice tasks to help them improve.\n\n";
                    $weakTopicsPrompt .= "Test Topic: " . ($analysisData['topic'] ?? 'General') . "\n\n";
                    $weakTopicsPrompt .= "Questions the student got wrong:\n";
                    foreach ($analysisData['wrongQuestions'] as $i => $wq) {
                        $weakTopicsPrompt .= ($i + 1) . ". " . $wq['question'] . "\n";
                    }
                    $weakTopicsPrompt .= "\nCreate practice tasks that:\n";
                    $weakTopicsPrompt .= "1. Target the specific weak areas\n";
                    $weakTopicsPrompt .= "2. Include a mix of multiple choice, fill-in-the-blank, and short answer questions\n";
                    $weakTopicsPrompt .= "3. Provide clear instructions\n";
                    $weakTopicsPrompt .= "4. Include answers at the end\n";
                    $weakTopicsPrompt .= "5. Make them slightly easier than the test to build confidence\n\n";
                    $weakTopicsPrompt .= "Format as a numbered list of tasks.";
                    
                    $ch2 = curl_init('https://api.openai.com/v1/chat/completions');
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_POST, true);
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode([
                        'model' => 'gpt-4',
                        'messages' => [
                            ['role' => 'system', 'content' => 'You are an expert tutor. Create targeted practice exercises to help students master difficult concepts.'],
                            ['role' => 'user', 'content' => $weakTopicsPrompt]
                        ],
                        'temperature' => 0.7,
                        'max_tokens' => 1500
                    ]));
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Authorization: Bearer ' . $apiKey
                    ]);
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 60);
                    
                    $response2 = curl_exec($ch2);
                    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    curl_close($ch2);
                    
                    if ($httpCode2 === 200) {
                        $data2 = json_decode($response2, true);
                        if (isset($data2['choices'][0]['message']['content'])) {
                            $practiceTasks = $data2['choices'][0]['message']['content'];
                        }
                    }
                }
                
                echo json_encode([
                    'analysis' => $analysis,
                    'practiceTasks' => $practiceTasks
                ]);
                exit;
            }
        }
    }
    
    echo json_encode(['analysis' => null, 'practiceTasks' => null]);
    exit;
}

// Get topics available to this student (from their enrolled groups)
$stmt = $pdo->prepare("SELECT ct.id, ct.title, ct.description, ct.due_date, ct.difficulty, ct.created_at,
                      c.schedule as group_name, co.course_name, co.course_code
                      FROM class_topics ct
                      JOIN classes c ON ct.class_id = c.id
                      JOIN courses co ON c.course_id = co.id
                      JOIN enrollments e ON c.id = e.class_id
                      WHERE e.student_id = ? AND e.status = 'active'
                      GROUP BY ct.id
                      ORDER BY ct.created_at DESC");
$stmt->execute([$studentId]);
$topics = $stmt->fetchAll();

// Get saved tests for this student
$savedTests = [];
try {
    $stmt = $pdo->prepare("SELECT st.*, ct.title as topic_title, co.course_name
                          FROM student_tests st
                          JOIN class_topics ct ON st.topic_id = ct.id
                          JOIN classes c ON ct.class_id = c.id
                          JOIN courses co ON c.course_id = co.id
                          WHERE st.student_id = ?
                          ORDER BY st.created_at DESC LIMIT 10");
    $stmt->execute([$studentId]);
    $savedTests = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
}

$navItems = getNavigationItems($userRole);
$pageTitle = 'AI Tests';
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
        
        .test-container { display: grid; grid-template-columns: 350px 1fr; gap: 24px; }
        @media (max-width: 900px) { .test-container { grid-template-columns: 1fr; } }
        
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
        
        .test-content {
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
        
        .topic-card {
            background: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid #e5e7eb;
            cursor: pointer;
        }
        .topic-card:hover { border-color: #3b82f6; }
        .topic-card.selected { border-color: #3b82f6; background: #eff6ff; }
        .topic-card h4 { font-size: 14px; color: #1f2937; margin-bottom: 4px; }
        .topic-card p { font-size: 12px; color: #6b7280; }
        
        .saved-tests { margin-top: 24px; }
        .saved-tests h3 { font-size: 16px; color: #1f2937; margin-bottom: 12px; }
        .test-item {
            background: white;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid #e5e7eb;
        }
        .test-item:hover { border-color: #3b82f6; }
        
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
                        AI Tests
                        <span class="ai-badge">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                <path d="M2 17l10 5 10-5"></path>
                                <path d="M2 12l10 5 10-5"></path>
                            </svg>
                            Powered by GPT-4
                        </span>
                    </h1>
                    
                    <div class="test-container">
                        <div class="input-panel">
                            <h2>Generate Test</h2>
                            
                            <?php if ($error): ?>
                                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                            <?php endif; ?>
                            
                            <?php if (count($topics) > 0): ?>
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="generate_test">
                                    <div class="form-group">
                                        <label for="topic_id">Select Topic *</label>
                                        <select name="topic_id" id="topic_id" required onchange="showTopicDifficulty(this)">
                                            <option value="">-- Choose a Topic --</option>
                                            <?php foreach ($topics as $topic): 
                                                $difficultyLabels = ['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'];
                                                $diffLabel = $difficultyLabels[$topic['difficulty']] ?? 'Medium';
                                            ?>
                                                <option value="<?php echo $topic['id']; ?>" data-difficulty="<?php echo htmlspecialchars($diffLabel); ?>" <?php echo ($_POST['topic_id'] ?? '') == $topic['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($topic['title'] . ' (' . $topic['course_code'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Test Difficulty <small style="color: #6b7280; font-weight: normal;">(set by teacher)</small></label>
                                        <div id="difficulty-display" style="padding: 10px 12px; background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 6px; font-weight: 500; color: #374151;">
                                            Select a topic to see difficulty
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="question_count">Number of Questions</label>
                                        <select name="question_count" id="question_count">
                                            <option value="5" <?php echo ($_POST['question_count'] ?? '') === '5' ? 'selected' : ''; ?>>5 questions</option>
                                            <option value="10" <?php echo ($_POST['question_count'] ?? '10') === '10' ? 'selected' : ''; ?>>10 questions</option>
                                            <option value="15" <?php echo ($_POST['question_count'] ?? '') === '15' ? 'selected' : ''; ?>>15 questions</option>
                                            <option value="20" <?php echo ($_POST['question_count'] ?? '') === '20' ? 'selected' : ''; ?>>20 questions</option>
                                        </select>
                                    </div>
                                    
                                    <script>
                                        function showTopicDifficulty(select) {
                                            const display = document.getElementById('difficulty-display');
                                            if (select.value) {
                                                const option = select.options[select.selectedIndex];
                                                const difficulty = option.getAttribute('data-difficulty');
                                                const colors = {
                                                    'Easy': '#10b981',
                                                    'Medium': '#f59e0b',
                                                    'Hard': '#ef4444'
                                                };
                                                display.textContent = difficulty;
                                                display.style.color = colors[difficulty] || '#374151';
                                                display.style.background = colors[difficulty] ? colors[difficulty] + '15' : '#f3f4f6';
                                                display.style.borderColor = colors[difficulty] || '#e5e7eb';
                                            } else {
                                                display.textContent = 'Select a topic to see difficulty';
                                                display.style.color = '#374151';
                                                display.style.background = '#f3f4f6';
                                                display.style.borderColor = '#e5e7eb';
                                            }
                                        }
                                        // Show difficulty on page load if topic is selected
                                        document.addEventListener('DOMContentLoaded', function() {
                                            const select = document.getElementById('topic_id');
                                            if (select.value) showTopicDifficulty(select);
                                        });
                                    </script>
                                    
                                    <button type="submit" class="btn btn-primary" <?php echo $loading ? 'disabled' : ''; ?>>
                                        <?php if ($loading): ?>
                                            <div class="spinner" style="width: 16px; height: 16px; border-width: 2px;"></div>
                                            Generating Test...
                                        <?php else: ?>
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                                <path d="M2 17l10 5 10-5"></path>
                                                <path d="M2 12l10 5 10-5"></path>
                                            </svg>
                                            Generate AI Test
                                        <?php endif; ?>
                                    </button>
                                </form>
                                
                                <?php if (count($savedTests) > 0): ?>
                                    <div class="saved-tests">
                                        <h3>Recent Tests</h3>
                                        <?php foreach (array_slice($savedTests, 0, 5) as $testItem): ?>
                                            <div class="test-item">
                                                <h4><?php echo htmlspecialchars($testItem['topic_title']); ?></h4>
                                                <p style="font-size: 12px; color: #6b7280;">
                                                    <?php echo htmlspecialchars($testItem['difficulty']); ?> • 
                                                    <?php echo $testItem['question_count']; ?> questions • 
                                                    <?php echo date('M d, Y', strtotime($testItem['created_at'])); ?>
                                                    <?php if ($testItem['score'] !== null): ?>
                                                        <span style="color: #10b981; font-weight: 600;"> • Score: <?php echo $testItem['score']; ?></span>
                                                    <?php else: ?>
                                                        <span style="color: #f59e0b;"> • Pending grading</span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="empty-state" style="padding: 24px;">
                                    <h3>No Topics Available</h3>
                                    <p>Your teacher hasn't added any topics for your groups yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="result-panel">
                            <?php if ($gradingResult): ?>
                                <h2>AI Grading Result</h2>
                                <div class="test-content" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5); border-color: #10b981;"><?php echo nl2br(htmlspecialchars($gradingResult)); ?></div>
                                <div style="margin-top: 16px;">
                                    <a href="ai-tests.php" class="btn btn-primary">Generate New Test</a>
                                </div>
                            <?php else: ?>
                                <h2>Generated Test</h2>
                                
                                <?php if ($loading): ?>
                                    <div class="loading">
                                        <div class="spinner"></div>
                                        <span><?php echo isset($_POST['action']) && $_POST['action'] === 'submit_test' ? 'AI is grading your test...' : 'AI is creating your test...'; ?></span>
                                    </div>
                                <?php elseif ($test): 
                                    $testData = json_decode($test, true);
                                ?>
                                    <div id="interactive-test" data-test='<?php echo htmlspecialchars($test); ?>' data-test-id="<?php echo $testId; ?>">
                                        <div class="test-header" style="background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
                                            <h2 style="margin: 0; font-size: 20px;"><?php echo htmlspecialchars($testData['title'] ?? 'AI Generated Test'); ?></h2>
                                            <p style="margin: 8px 0 0 0; opacity: 0.9;"><?php echo htmlspecialchars($testData['instructions'] ?? 'Select the best answer for each question.'); ?></p>
                                            <div style="margin-top: 12px; display: flex; gap: 16px; font-size: 13px;">
                                                <span>Total Points: <?php echo $testData['totalPoints'] ?? 100; ?></span>
                                                <span>Questions: <?php echo count($testData['questions'] ?? []); ?></span>
                                                <span>Time: <?php echo htmlspecialchars($testData['estimatedTime'] ?? '20 min'); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div id="question-container">
                                            <!-- Questions will be rendered here by JavaScript -->
                                        </div>
                                        
                                        <div id="test-results" style="display: none; background: linear-gradient(135deg, #ecfdf5, #d1fae5); border: 2px solid #10b981; border-radius: 12px; padding: 24px; margin-top: 20px;">
                                            <h3 style="color: #065f46; margin-bottom: 16px;">Test Complete!</h3>
                                            <div id="score-display" style="font-size: 32px; font-weight: bold; color: #10b981; margin-bottom: 16px;"></div>
                                            <div id="results-details"></div>
                                            <button onclick="location.reload()" class="btn btn-primary" style="margin-top: 16px;">Generate New Test</button>
                                        </div>
                                    </div>
                                    
                                    <style>
                                        .question-card {
                                            background: white;
                                            border: 2px solid #e5e7eb;
                                            border-radius: 12px;
                                            padding: 24px;
                                            margin-bottom: 16px;
                                            transition: all 0.3s ease;
                                        }
                                        .question-card.active {
                                            border-color: #3b82f6;
                                            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
                                        }
                                        .question-card.answered {
                                            border-color: #10b981;
                                            opacity: 0.7;
                                        }
                                        .question-card.correct {
                                            border-color: #10b981;
                                            background: #ecfdf5;
                                        }
                                        .question-card.incorrect {
                                            border-color: #ef4444;
                                            background: #fef2f2;
                                        }
                                        .question-number {
                                            display: inline-flex;
                                            align-items: center;
                                            justify-content: center;
                                            width: 32px;
                                            height: 32px;
                                            background: #3b82f6;
                                            color: white;
                                            border-radius: 50%;
                                            font-weight: 600;
                                            margin-right: 12px;
                                        }
                                        .question-text {
                                            font-size: 16px;
                                            font-weight: 500;
                                            color: #1f2937;
                                            margin-bottom: 16px;
                                        }
                                        .options-list {
                                            display: flex;
                                            flex-direction: column;
                                            gap: 10px;
                                        }
                                        .option-btn {
                                            display: flex;
                                            align-items: center;
                                            padding: 14px 16px;
                                            background: #f9fafb;
                                            border: 2px solid #e5e7eb;
                                            border-radius: 8px;
                                            cursor: pointer;
                                            transition: all 0.2s ease;
                                            text-align: left;
                                            font-size: 14px;
                                        }
                                        .option-btn:hover {
                                            border-color: #3b82f6;
                                            background: #eff6ff;
                                        }
                                        .option-btn.selected {
                                            border-color: #3b82f6;
                                            background: #3b82f6;
                                            color: white;
                                        }
                                        .option-btn.correct {
                                            border-color: #10b981;
                                            background: #10b981;
                                            color: white;
                                        }
                                        .option-btn.incorrect {
                                            border-color: #ef4444;
                                            background: #ef4444;
                                            color: white;
                                        }
                                        .option-letter {
                                            display: inline-flex;
                                            align-items: center;
                                            justify-content: center;
                                            width: 28px;
                                            height: 28px;
                                            background: white;
                                            border-radius: 6px;
                                            font-weight: 600;
                                            margin-right: 12px;
                                            color: #374151;
                                        }
                                        .option-btn.selected .option-letter,
                                        .option-btn.correct .option-letter,
                                        .option-btn.incorrect .option-letter {
                                            background: rgba(255,255,255,0.2);
                                            color: white;
                                        }
                                        .progress-bar {
                                            background: #e5e7eb;
                                            border-radius: 10px;
                                            height: 8px;
                                            margin-bottom: 20px;
                                            overflow: hidden;
                                        }
                                        .progress-fill {
                                            background: linear-gradient(90deg, #1e40af, #3b82f6);
                                            height: 100%;
                                            border-radius: 10px;
                                            transition: width 0.3s ease;
                                        }
                                        .progress-text {
                                            text-align: center;
                                            font-size: 14px;
                                            color: #6b7280;
                                            margin-bottom: 8px;
                                        }
                                    </style>
                                    
                                    <script>
                                        (function() {
                                            const testContainer = document.getElementById('interactive-test');
                                            const testData = JSON.parse(testContainer.dataset.test);
                                            const testId = testContainer.dataset.testId;
                                            const questions = testData.questions || [];
                                            let currentQuestion = 0;
                                            let answers = [];
                                            let scored = false;
                                            
                                            function renderQuestion(index) {
                                                const container = document.getElementById('question-container');
                                                const q = questions[index];
                                                
                                                let html = '<div class="progress-text">Question ' + (index + 1) + ' of ' + questions.length + '</div>';
                                                html += '<div class="progress-bar"><div class="progress-fill" style="width: ' + ((index / questions.length) * 100) + '%"></div></div>';
                                                
                                                html += '<div class="question-card active" id="q-' + index + '">';
                                                html += '<div class="question-text"><span class="question-number">' + (index + 1) + '</span>' + escapeHtml(q.question) + '</div>';
                                                html += '<div class="options-list">';
                                                
                                                q.options.forEach((opt, i) => {
                                                    const letter = String.fromCharCode(65 + i);
                                                    const isSelected = answers[index] === i;
                                                    const isCorrect = scored && i === q.correctAnswer;
                                                    const isWrong = scored && isSelected && i !== q.correctAnswer;
                                                    
                                                    let btnClass = 'option-btn';
                                                    if (isCorrect) btnClass += ' correct';
                                                    else if (isWrong) btnClass += ' incorrect';
                                                    else if (isSelected) btnClass += ' selected';
                                                    
                                                    html += '<button type="button" class="' + btnClass + '" onclick="selectOption(' + index + ', ' + i + ')" ' + (scored ? 'disabled' : '') + '>';
                                                    html += '<span class="option-letter">' + letter + '</span>';
                                                    html += escapeHtml(opt);
                                                    if (isCorrect) html += ' ✓';
                                                    if (isWrong) html += ' ✗';
                                                    html += '</button>';
                                                });
                                                
                                                html += '</div></div>';
                                                
                                                // Show navigation or submit
                                                if (index === questions.length - 1 && !scored) {
                                                    html += '<button onclick="submitTest()" class="btn btn-primary" style="width: 100%; margin-top: 16px;">Submit Test</button>';
                                                } else if (index < questions.length - 1 && answers[index] !== undefined && !scored) {
                                                    html += '<button onclick="nextQuestion()" class="btn btn-primary" style="width: 100%; margin-top: 16px;">Next Question →</button>';
                                                }
                                                
                                                container.innerHTML = html;
                                            }
                                            
                                            window.selectOption = function(qIndex, optionIndex) {
                                                if (scored) return;
                                                answers[qIndex] = optionIndex;
                                                renderQuestion(qIndex);
                                                
                                                // Auto-advance after short delay if not last question
                                                if (qIndex < questions.length - 1) {
                                                    setTimeout(() => {
                                                        currentQuestion++;
                                                        renderQuestion(currentQuestion);
                                                    }, 500);
                                                }
                                            };
                                            
                                            window.nextQuestion = function() {
                                                if (currentQuestion < questions.length - 1) {
                                                    currentQuestion++;
                                                    renderQuestion(currentQuestion);
                                                }
                                            };
                                            
                                            window.submitTest = function() {
                                                scored = true;
                                                let totalScore = 0;
                                                let maxScore = 0;
                                                let correct = 0;
                                                let wrongQuestions = [];
                                                
                                                questions.forEach((q, i) => {
                                                    maxScore += q.points;
                                                    if (answers[i] === q.correctAnswer) {
                                                        totalScore += q.points;
                                                        correct++;
                                                    } else {
                                                        wrongQuestions.push({
                                                            question: q.question,
                                                            userAnswer: answers[i],
                                                            correctAnswer: q.correctAnswer,
                                                            options: q.options
                                                        });
                                                    }
                                                });
                                                
                                                const percentage = Math.round((totalScore / maxScore) * 100);
                                                
                                                // Show all questions with results
                                                let html = '<div class="progress-text">Test Complete!</div>';
                                                html += '<div class="progress-bar"><div class="progress-fill" style="width: 100%"></div></div>';
                                                
                                                questions.forEach((q, i) => {
                                                    const isCorrect = answers[i] === q.correctAnswer;
                                                    html += '<div class="question-card ' + (isCorrect ? 'correct' : 'incorrect') + '">';
                                                    html += '<div class="question-text"><span class="question-number">' + (i + 1) + '</span>' + escapeHtml(q.question) + '</div>';
                                                    html += '<div style="margin-bottom: 12px; font-size: 13px; color: #6b7280;">Your answer: ' + (answers[i] !== undefined ? String.fromCharCode(65 + answers[i]) : 'Not answered') + ' | Correct: ' + String.fromCharCode(65 + q.correctAnswer) + '</div>';
                                                    html += '</div>';
                                                });
                                                
                                                document.getElementById('question-container').innerHTML = html;
                                                
                                                // Show results
                                                document.getElementById('test-results').style.display = 'block';
                                                document.getElementById('score-display').textContent = percentage + '% (' + correct + '/' + questions.length + ')';
                                                document.getElementById('results-details').innerHTML = '<p>You scored <strong>' + totalScore + '</strong> out of <strong>' + maxScore + '</strong> points</p>';
                                                
                                                // Add AI Analysis section
                                                const analysisDiv = document.createElement('div');
                                                analysisDiv.id = 'ai-analysis';
                                                analysisDiv.style.cssText = 'margin-top: 24px; background: white; border: 2px solid #8b5cf6; border-radius: 12px; padding: 24px;';
                                                analysisDiv.innerHTML = '<div class="loading" style="padding: 24px;"><div class="spinner"></div><span>AI is analyzing your performance...</span></div>';
                                                document.getElementById('test-results').appendChild(analysisDiv);
                                                
                                                // Call AI for analysis
                                                const analysisData = {
                                                    topic: testData.title,
                                                    totalQuestions: questions.length,
                                                    correctAnswers: correct,
                                                    wrongAnswers: questions.length - correct,
                                                    percentage: percentage,
                                                    wrongQuestions: wrongQuestions
                                                };
                                                
                                                fetch('ai-tests.php', {
                                                    method: 'POST',
                                                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                                    body: 'action=analyze_test&test_id=' + testId + '&score=' + totalScore + '&analysis_data=' + encodeURIComponent(JSON.stringify(analysisData))
                                                })
                                                .then(response => response.json())
                                                .then(data => {
                                                    if (data.analysis) {
                                                        let analysisHtml = '<h3 style="color: #7c3aed; margin-bottom: 16px;">📊 AI Performance Analysis</h3>';
                                                        analysisHtml += '<div style="line-height: 1.7; white-space: pre-wrap; margin-bottom: 24px;">' + escapeHtml(data.analysis).replace(/\n/g, '<br>') + '</div>';
                                                        
                                                        // Add practice tasks if available
                                                        if (data.practiceTasks) {
                                                            analysisHtml += '<div style="background: linear-gradient(135deg, #fef3c7, #fde68a); border: 2px solid #f59e0b; border-radius: 12px; padding: 20px; margin-top: 20px;">';
                                                            analysisHtml += '<h4 style="color: #92400e; margin-bottom: 12px;">📝 Additional Practice Tasks</h4>';
                                                            analysisHtml += '<p style="color: #78350f; font-size: 13px; margin-bottom: 16px;">Complete these exercises to strengthen your understanding of weak topics:</p>';
                                                            analysisHtml += '<div style="background: white; border-radius: 8px; padding: 16px; line-height: 1.7; white-space: pre-wrap;">' + escapeHtml(data.practiceTasks).replace(/\n/g, '<br>') + '</div>';
                                                            analysisHtml += '</div>';
                                                        }
                                                        
                                                        analysisDiv.innerHTML = analysisHtml;
                                                        
                                                        // Save analysis and practice tasks to database
                                                        fetch('ai-tests.php', {
                                                            method: 'POST',
                                                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                                            body: 'action=save_analysis&test_id=' + testId + '&analysis=' + encodeURIComponent(data.analysis) + '&practice_tasks=' + encodeURIComponent(data.practiceTasks || '')
                                                        });
                                                    } else {
                                                        analysisDiv.style.display = 'none';
                                                    }
                                                })
                                                .catch(() => {
                                                    analysisDiv.style.display = 'none';
                                                });
                                                
                                                // Save results to server
                                                fetch('ai-tests.php', {
                                                    method: 'POST',
                                                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                                    body: 'action=save_score&test_id=' + testId + '&score=' + totalScore + '&answers=' + encodeURIComponent(JSON.stringify(answers))
                                                });
                                            };
                                            
                                            function escapeHtml(text) {
                                                const div = document.createElement('div');
                                                div.textContent = text;
                                                return div.innerHTML;
                                            }
                                            
                                            // Start with first question
                                            renderQuestion(0);
                                        })();
                                    </script>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5" style="margin-bottom: 16px;">
                                            <path d="M9 11l3 3L22 4"></path>
                                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                                        </svg>
                                        <h3>Ready to Generate</h3>
                                        <p>Select a topic from your classes and click "Generate AI Test" to create a personalized test.</p>
                                    </div>
                                <?php endif; ?>
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
