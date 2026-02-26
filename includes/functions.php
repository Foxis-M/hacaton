<?php
// Helper functions for the dashboard

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/translations.php';

// Get current language from session or default to Russian
$lang = $_SESSION['language'] ?? 'ru'; // Set default to Russian

// SVG Icons
function getIcon($name) {
    $icons = [
        'users' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'userCog' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><circle cx="19" cy="11" r="2"></circle><path d="M19 8v1"></path><path d="M19 13v1"></path><path d="m21.6 9.5-.87.5"></path><path d="m16.27 12-.87.5"></path><path d="m21.6 12.5-.87-.5"></path><path d="m16.27 10-.87-.5"></path></svg>',
        'settings' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.1a2 2 0 0 1-1-1.72v-.51a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
        'shield' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
        'bookOpen' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>',
        'clipboardList' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path></svg>',
        'brain' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 1.98-3A2.5 2.5 0 0 1 9.5 2Z"></path><path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-1.98-3A2.5 2.5 0 0 0 14.5 2Z"></path></svg>',
        'calendar' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
        'notebookPen' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13.4 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7.4"></path><path d="M2 6h4"></path><path d="M2 10h4"></path><path d="M2 14h4"></path><path d="M2 18h4"></path><path d="M18.4 2.6a2.17 2.17 0 0 1 3 3L16 11l-4 1 1-4Z"></path></svg>',
        'award' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>',
        'bookCopy' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 7v14a2 2 0 0 0 2 2h14"></path><path d="M20 2H6a2 2 0 0 0-2 2v16"></path><path d="M22 6H8a2 2 0 0 0-2 2v12"></path></svg>',
        'calendarDays' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><path d="M8 14h.01"></path><path d="M12 14h.01"></path><path d="M16 14h.01"></path><path d="M8 18h.01"></path><path d="M12 18h.01"></path><path d="M16 18h.01"></path></svg>',
        'clock' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
        'barChart' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"></path><path d="M18 17V9"></path><path d="M13 17V5"></path><path d="M8 17v-3"></path></svg>',
        'library' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m16 6 4 14"></path><path d="M12 6v14"></path><path d="M8 8v12"></path><path d="M4 4v16"></path></svg>',
        'pieChart' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>',
        'graduationCap' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>',
        'checkCircle' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
        'alertTriangle' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
        'alertCircle' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
        'info' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>',
        'trendingUp' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>',
        'trendingDown' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>',
        'moreHorizontal' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>'
    ];
    
    return $icons[$name] ?? '';
}

// Get welcome message based on time
function getWelcomeMessage($name) {
    $hour = date('H');
    $greeting = 'Good morning';
    if ($hour >= 12 && $hour < 17) $greeting = 'Good afternoon';
    if ($hour >= 17) $greeting = 'Good evening';
    $translatedGreeting = getTranslation($greeting, 'ru');
    return "$translatedGreeting, $name!";
}

// Get role description
function getRoleDescription($role) {
    $descriptions = [
        'admin' => 'Manage students, teachers, and system settings from your administrator dashboard.',
        'teacher' => 'Manage your classes, grades, and access AI-powered teaching tools.',
        'student' => 'View your grades, homework, schedule, and track your academic progress.'
    ];
    $description = $descriptions[$role] ?? '';
    return getTranslation($description, 'ru');
}

// Get navigation items based on role
function getNavigationItems($role) {
    $navItems = [
        'admin' => [
            ['id' => 'user-approvals', 'label' => getTranslation('User Approvals', 'ru'), 'path' => '/admin/user-approvals.php', 'icon' => getIcon('shield')],
            ['id' => 'student-management', 'label' => getTranslation('Student Management', 'ru'), 'path' => '/students.php', 'icon' => getIcon('users')],
            ['id' => 'teacher-management', 'label' => getTranslation('Teacher Management', 'ru'), 'path' => '/teachers.php', 'icon' => getIcon('userCog')],
            ['id' => 'system-settings', 'label' => getTranslation('System Settings', 'ru'), 'path' => '/system.php', 'icon' => getIcon('settings')],
            ['id' => 'user-access', 'label' => getTranslation('User Access Control', 'ru'), 'path' => '/access.php', 'icon' => getIcon('shield')],
            ['id' => 'ai-schedule', 'label' => getTranslation('AI Schedule Generator', 'ru'), 'path' => '/schedule-generator.php', 'icon' => getIcon('calendar')],
            ['id' => 'knowledge-base', 'label' => getTranslation('Knowledge Base', 'ru'), 'path' => '/knowledge.php', 'icon' => getIcon('library')],
            ['id' => 'analytics', 'label' => getTranslation('Analytics', 'ru'), 'path' => '/analytics.php', 'icon' => getIcon('pieChart')],
            ['id' => 'academic-process', 'label' => getTranslation('Academic Process', 'ru'), 'path' => '/academic.php', 'icon' => getIcon('graduationCap')]
        ],
        'teacher' => [
            ['id' => 'my-classes', 'label' => getTranslation('My Groups', 'ru'), 'path' => '/classes.php', 'icon' => getIcon('bookOpen')],
            ['id' => 'grade-management', 'label' => getTranslation('Grade Management', 'ru'), 'path' => '/grades.php', 'icon' => getIcon('clipboardList')],
            ['id' => 'ai-lesson-planner', 'label' => getTranslation('AI Lesson Planner', 'ru'), 'path' => '/lesson-planner.php', 'icon' => getIcon('brain')],
            ['id' => 'journals', 'label' => getTranslation('Journals', 'ru'), 'path' => '/journals.php', 'icon' => getIcon('notebookPen')],
            ['id' => 'knowledge-base', 'label' => getTranslation('Knowledge Base', 'ru'), 'path' => '/knowledge.php', 'icon' => getIcon('library')],
            ['id' => 'academic-process', 'label' => getTranslation('Academic Process', 'ru'), 'path' => '/academic.php', 'icon' => getIcon('graduationCap')]
        ],
        'student' => [
            ['id' => 'my-grades', 'label' => getTranslation('My Grades', 'ru'), 'path' => '/my-grades.php', 'icon' => getIcon('award')],
            ['id' => 'homework', 'label' => getTranslation('Homework', 'ru'), 'path' => '/homework.php', 'icon' => getIcon('bookCopy')],
            ['id' => 'ai-tests', 'label' => getTranslation('AI Tests', 'ru'), 'path' => '/ai-tests.php', 'icon' => getIcon('brain')],
            ['id' => 'my-schedule', 'label' => getTranslation('My Schedule', 'ru'), 'path' => '/my-schedule.php', 'icon' => getIcon('calendarDays')],
            ['id' => 'deadlines', 'label' => getTranslation('Deadlines', 'ru'), 'path' => '/deadlines.php', 'icon' => getIcon('clock')],
            ['id' => 'statistics', 'label' => getTranslation('Statistics', 'ru'), 'path' => '/statistics.php', 'icon' => getIcon('barChart')],
            ['id' => 'knowledge-base', 'label' => getTranslation('Knowledge Base', 'ru'), 'path' => '/knowledge.php', 'icon' => getIcon('library')]
        ]
    ];
    
    return $navItems[$role] ?? [];
}

// Get dashboard data based on role
function getDashboardData($role, $userId) {
    $pdo = getDBConnection();
    
    $data = [
        'stats' => [],
        'quickActions' => [],
        'activities' => []
    ];
    
    if ($role === 'admin') {
        // Get real stats from database
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
        $studentCount = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'");
        $teacherCount = $stmt->fetch()['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM courses WHERE is_active = TRUE");
        $courseCount = $stmt->fetch()['count'];
        
        $data['stats'] = [
            [
                'title' => getTranslation('Total Students', 'ru'),
                'value' => number_format($studentCount),
                'change' => getTranslation('+12%', 'ru'),
                'changeType' => 'positive',
                'icon' => getIcon('users'),
                'trendIcon' => getIcon('trendingUp')
            ],
            [
                'title' => getTranslation('Total Teachers', 'ru'),
                'value' => number_format($teacherCount),
                'change' => getTranslation('+3', 'ru'),
                'changeType' => 'positive',
                'icon' => getIcon('userCog'),
                'trendIcon' => getIcon('trendingUp')
            ],
            [
                'title' => getTranslation('Active Courses', 'ru'),
                'value' => number_format($courseCount),
                'change' => getTranslation('+5', 'ru'),
                'changeType' => 'positive',
                'icon' => getIcon('bookOpen'),
                'trendIcon' => getIcon('trendingUp')
            ],
            [
                'title' => getTranslation('System Health', 'ru'),
                'value' => getTranslation('99.9%', 'ru'),
                'change' => getTranslation('Stable', 'ru'),
                'changeType' => 'neutral',
                'icon' => getIcon('checkCircle'),
                'trendIcon' => getIcon('moreHorizontal')
            ]
        ];
        
        $data['quickActions'] = [
            ['label' => getTranslation('Add Student', 'ru'), 'icon' => getIcon('users'), 'color' => '#1e40af'],
            ['label' => getTranslation('Add Teacher', 'ru'), 'icon' => getIcon('userCog'), 'color' => '#1e40af'],
            ['label' => getTranslation('System Settings', 'ru'), 'icon' => getIcon('settings'), 'color' => '#64748b'],
            ['label' => getTranslation('View Reports', 'ru'), 'icon' => getIcon('pieChart'), 'color' => '#10b981']
        ];
        
    } elseif ($role === 'teacher') {
        $data['stats'] = [
            [
                'title' => getTranslation("Today's Classes", 'ru'),
                'value' => '4',
                'change' => getTranslation('2 completed', 'ru'),
                'changeType' => 'neutral',
                'icon' => getIcon('calendar'),
                'trendIcon' => getIcon('moreHorizontal')
            ],
            [
                'title' => getTranslation('Pending Grades', 'ru'),
                'value' => '23',
                'change' => getTranslation('Due tomorrow', 'ru'),
                'changeType' => 'warning',
                'icon' => getIcon('clipboardList'),
                'trendIcon' => getIcon('alertTriangle')
            ],
            [
                'title' => getTranslation('Total Students', 'ru'),
                'value' => '156',
                'change' => getTranslation('In 6 classes', 'ru'),
                'changeType' => 'neutral',
                'icon' => getIcon('users'),
                'trendIcon' => getIcon('moreHorizontal')
            ],
            [
                'title' => getTranslation('AI Tasks', 'ru'),
                'value' => '3',
                'change' => getTranslation('Ready to generate', 'ru'),
                'changeType' => 'positive',
                'icon' => getIcon('brain'),
                'trendIcon' => getIcon('trendingUp')
            ]
        ];
        
        $data['quickActions'] = [
            ['label' => getTranslation('Create Lesson', 'ru'), 'icon' => getIcon('brain'), 'color' => '#8b5cf6'],
            ['label' => getTranslation('Enter Grades', 'ru'), 'icon' => getIcon('clipboardList'), 'color' => '#1e40af'],
            ['label' => getTranslation('Generate Schedule', 'ru'), 'icon' => getIcon('calendar'), 'color' => '#10b981'],
            ['label' => getTranslation('View Journals', 'ru'), 'icon' => getIcon('notebookPen'), 'color' => '#f59e0b']
        ];
        
    } else { // student
        $data['stats'] = [
            [
                'title' => getTranslation('Current GPA', 'ru'),
                'value' => '3.8',
                'change' => getTranslation('+0.2', 'ru'),
                'changeType' => 'positive',
                'icon' => getIcon('award'),
                'trendIcon' => getIcon('trendingUp')
            ],
            [
                'title' => getTranslation('Assignments Due', 'ru'),
                'value' => '5',
                'change' => getTranslation('2 due today', 'ru'),
                'changeType' => 'warning',
                'icon' => getIcon('bookCopy'),
                'trendIcon' => getIcon('alertTriangle')
            ],
            [
                'title' => getTranslation('Attendance', 'ru'),
                'value' => '94%',
                'change' => getTranslation('Excellent', 'ru'),
                'changeType' => 'positive',
                'icon' => getIcon('checkCircle'),
                'trendIcon' => getIcon('trendingUp')
            ],
            [
                'title' => getTranslation('Next Class', 'ru'),
                'value' => getTranslation('10:30 AM', 'ru'),
                'change' => getTranslation('Mathematics', 'ru'),
                'changeType' => 'neutral',
                'icon' => getIcon('clock'),
                'trendIcon' => getIcon('moreHorizontal')
            ]
        ];
        
        $data['quickActions'] = [
            ['label' => getTranslation('View Grades', 'ru'), 'icon' => getIcon('award'), 'color' => '#10b981'],
            ['label' => getTranslation('Homework', 'ru'), 'icon' => getIcon('bookCopy'), 'color' => '#1e40af'],
            ['label' => getTranslation('My Schedule', 'ru'), 'icon' => getIcon('calendarDays'), 'color' => '#8b5cf6'],
            ['label' => getTranslation('Knowledge Base', 'ru'), 'icon' => getIcon('library'), 'color' => '#f59e0b']
        ];
    }
    
    // Get recent activities from database
    $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $activities = $stmt->fetchAll();
    
    if (empty($activities)) {
        // Default activities if none in database
        $activities = getDefaultActivities($role);
    } else {
        $activities = formatActivities($activities);
    }
    
    $data['activities'] = $activities;
    
    return $data;
}

// Get default activities
function getDefaultActivities($role) {
    $activities = [
        'admin' => [
            ['title' => getTranslation('New Student Registration', 'ru'), 'description' => getTranslation('5 new students registered today', 'ru'), 'time' => '30m ago', 'type' => 'info'],
            ['title' => getTranslation('System Backup Completed', 'ru'), 'description' => getTranslation('Automated backup finished successfully', 'ru'), 'time' => '2h ago', 'type' => 'success'],
            ['title' => getTranslation('Teacher Account Approved', 'ru'), 'description' => getTranslation('Dr. Smith\'s account has been verified', 'ru'), 'time' => '4h ago', 'type' => 'success'],
            ['title' => getTranslation('Low Disk Space Warning', 'ru'), 'description' => getTranslation('Server storage at 85% capacity', 'ru'), 'time' => '6h ago', 'type' => 'warning']
        ],
        'teacher' => [
            ['title' => getTranslation('New Submission', 'ru'), 'description' => getTranslation('3 students submitted homework', 'ru'), 'time' => '15m ago', 'type' => 'info'],
            ['title' => getTranslation('AI Lesson Plan Ready', 'ru'), 'description' => getTranslation('Your lesson plan for next week is generated', 'ru'), 'time' => '45m ago', 'type' => 'success'],
            ['title' => getTranslation('Grade Deadline', 'ru'), 'description' => getTranslation('Final grades due in 2 days', 'ru'), 'time' => '3h ago', 'type' => 'warning'],
            ['title' => getTranslation('Parent Meeting Scheduled', 'ru'), 'description' => getTranslation('Meeting with Johnson family at 3 PM', 'ru'), 'time' => '5h ago', 'type' => 'info']
        ],
        'student' => [
            ['title' => getTranslation('New Grade Posted', 'ru'), 'description' => getTranslation('Mathematics quiz: 92/100', 'ru'), 'time' => '20m ago', 'type' => 'success'],
            ['title' => getTranslation('Homework Reminder', 'ru'), 'description' => getTranslation('Physics assignment due tomorrow', 'ru'), 'time' => '1h ago', 'type' => 'warning'],
            ['title' => getTranslation('Class Cancelled', 'ru'), 'description' => getTranslation('History class on Friday is cancelled', 'ru'), 'time' => '2h ago', 'type' => 'info'],
            ['title' => getTranslation('Achievement Unlocked', 'ru'), 'description' => getTranslation('Perfect attendance for 30 days!', 'ru'), 'time' => '8h ago', 'type' => 'success']
        ]
    ];
    
    return formatActivities($activities[$role] ?? $activities['student']);
}

// Format activities with icons and colors
function formatActivities($activities) {
    $formatted = [];
    
    foreach ($activities as $activity) {
        $type = $activity['type'] ?? 'info';
        
        $iconMap = [
            'success' => 'checkCircle',
            'warning' => 'alertTriangle',
            'error' => 'alertCircle',
            'info' => 'info'
        ];
        
        $colorMap = [
            'success' => '#10b981',
            'warning' => '#f59e0b',
            'error' => '#ef4444',
            'info' => '#3b82f6'
        ];
        
        $formatted[] = [
            'title' => $activity['title'] ?? $activity['action'] ?? 'Activity',
            'description' => $activity['description'] ?? $activity['description'] ?? '',
            'time' => $activity['time'] ?? timeAgo($activity['created_at'] ?? date('Y-m-d H:i:s')),
            'type' => $type,
            'icon' => getIcon($iconMap[$type]),
            'color' => $colorMap[$type]
        ];
    }
    
    return $formatted;
}

// Time ago helper
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}
