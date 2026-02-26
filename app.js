// AGKB College Dashboard - Pure JavaScript Implementation

// User data for each role
const users = {
    admin: {
        id: '1',
        name: 'Admin User',
        email: 'admin@agkb.edu',
        role: 'admin',
        avatar: 'AU'
    },
    teacher: {
        id: '2',
        name: 'Teacher User',
        email: 'teacher@agkb.edu',
        role: 'teacher',
        avatar: 'TU'
    },
    student: {
        id: '3',
        name: 'Student User',
        email: 'student@agkb.edu',
        role: 'student',
        avatar: 'SU'
    }
};

// Navigation configuration
const navigationConfig = {
    admin: [
        { id: 'student-management', label: 'Student Management', path: 'students', icon: 'users' },
        { id: 'teacher-management', label: 'Teacher Management', path: 'teachers', icon: 'userCog' },
        { id: 'system-settings', label: 'System Settings', path: 'system', icon: 'settings' },
        { id: 'user-access', label: 'User Access Control', path: 'access', icon: 'shield' },
        { id: 'knowledge-base', label: 'Knowledge Base', path: 'knowledge', icon: 'library' },
        { id: 'analytics', label: 'Analytics', path: 'analytics', icon: 'pieChart' },
        { id: 'academic-process', label: 'Academic Process', path: 'academic', icon: 'graduationCap' }
    ],
    teacher: [
        { id: 'my-classes', label: 'My Classes', path: 'classes', icon: 'bookOpen' },
        { id: 'grade-management', label: 'Grade Management', path: 'grades', icon: 'clipboardList' },
        { id: 'ai-lesson-planner', label: 'AI Lesson Planner', path: 'lesson-planner', icon: 'brain' },
        { id: 'ai-schedule', label: 'AI Schedule Generator', path: 'schedule-generator', icon: 'calendar' },
        { id: 'journals', label: 'Journals', path: 'journals', icon: 'notebookPen' },
        { id: 'knowledge-base', label: 'Knowledge Base', path: 'knowledge', icon: 'library' },
        { id: 'analytics', label: 'Analytics', path: 'analytics', icon: 'pieChart' },
        { id: 'academic-process', label: 'Academic Process', path: 'academic', icon: 'graduationCap' }
    ],
    student: [
        { id: 'my-grades', label: 'My Grades', path: 'my-grades', icon: 'award' },
        { id: 'homework', label: 'Homework', path: 'homework', icon: 'bookCopy' },
        { id: 'my-schedule', label: 'My Schedule', path: 'my-schedule', icon: 'calendarDays' },
        { id: 'deadlines', label: 'Deadlines', path: 'deadlines', icon: 'clock' },
        { id: 'statistics', label: 'Statistics', path: 'statistics', icon: 'barChart' },
        { id: 'knowledge-base', label: 'Knowledge Base', path: 'knowledge', icon: 'library' }
    ]
};

// SVG Icons
const icons = {
    users: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
    userCog: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><circle cx="19" cy="11" r="2"></circle><path d="M19 8v1"></path><path d="M19 13v1"></path><path d="m21.6 9.5-.87.5"></path><path d="m16.27 12-.87.5"></path><path d="m21.6 12.5-.87-.5"></path><path d="m16.27 10-.87-.5"></path></svg>',
    settings: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.1a2 2 0 0 1-1-1.72v-.51a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
    shield: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
    bookOpen: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>',
    clipboardList: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M12 11h4"></path><path d="M12 16h4"></path><path d="M8 11h.01"></path><path d="M8 16h.01"></path></svg>',
    brain: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 1.98-3A2.5 2.5 0 0 1 9.5 2Z"></path><path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-1.98-3A2.5 2.5 0 0 0 14.5 2Z"></path></svg>',
    calendar: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
    notebookPen: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13.4 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7.4"></path><path d="M2 6h4"></path><path d="M2 10h4"></path><path d="M2 14h4"></path><path d="M2 18h4"></path><path d="M18.4 2.6a2.17 2.17 0 0 1 3 3L16 11l-4 1 1-4Z"></path></svg>',
    award: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>',
    bookCopy: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 7v14a2 2 0 0 0 2 2h14"></path><path d="M20 2H6a2 2 0 0 0-2 2v16"></path><path d="M22 6H8a2 2 0 0 0-2 2v12"></path></svg>',
    calendarDays: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line><path d="M8 14h.01"></path><path d="M12 14h.01"></path><path d="M16 14h.01"></path><path d="M8 18h.01"></path><path d="M12 18h.01"></path><path d="M16 18h.01"></path></svg>',
    clock: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
    barChart: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"></path><path d="M18 17V9"></path><path d="M13 17V5"></path><path d="M8 17v-3"></path></svg>',
    library: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m16 6 4 14"></path><path d="M12 6v14"></path><path d="M8 8v12"></path><path d="M4 4v16"></path></svg>',
    pieChart: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>',
    graduationCap: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>',
    checkCircle: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
    alertTriangle: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
    alertCircle: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
    info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>',
    trendingUp: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>',
    trendingDown: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"></polyline><polyline points="17 18 23 18 23 12"></polyline></svg>',
    moreHorizontal: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="5" cy="12" r="1"></circle></svg>'
};

// Stats data for each role
const statsData = {
    admin: [
        { id: '1', title: 'Total Students', value: '1,245', change: '+12%', changeType: 'positive', icon: 'users' },
        { id: '2', title: 'Total Teachers', value: '86', change: '+3', changeType: 'positive', icon: 'userCog' },
        { id: '3', title: 'Active Courses', value: '142', change: '+5', changeType: 'positive', icon: 'bookOpen' },
        { id: '4', title: 'System Health', value: '99.9%', change: 'Stable', changeType: 'neutral', icon: 'checkCircle' }
    ],
    teacher: [
        { id: '1', title: "Today's Classes", value: '4', change: '2 completed', changeType: 'neutral', icon: 'calendar' },
        { id: '2', title: 'Pending Grades', value: '23', change: 'Due tomorrow', changeType: 'warning', icon: 'clipboardList' },
        { id: '3', title: 'Total Students', value: '156', change: 'In 6 classes', changeType: 'neutral', icon: 'users' },
        { id: '4', title: 'AI Tasks', value: '3', change: 'Ready to generate', changeType: 'positive', icon: 'brain' }
    ],
    student: [
        { id: '1', title: 'Current GPA', value: '3.8', change: '+0.2', changeType: 'positive', icon: 'award' },
        { id: '2', title: 'Assignments Due', value: '5', change: '2 due today', changeType: 'warning', icon: 'bookCopy' },
        { id: '3', title: 'Attendance', value: '94%', change: 'Excellent', changeType: 'positive', icon: 'checkCircle' },
        { id: '4', title: 'Next Class', value: '10:30 AM', change: 'Mathematics', changeType: 'neutral', icon: 'clock' }
    ]
};

// Quick actions for each role
const quickActionsData = {
    admin: [
        { label: 'Add Student', icon: 'users', color: '#1e40af' },
        { label: 'Add Teacher', icon: 'userCog', color: '#1e40af' },
        { label: 'System Settings', icon: 'settings', color: '#64748b' },
        { label: 'View Reports', icon: 'pieChart', color: '#10b981' }
    ],
    teacher: [
        { label: 'Create Lesson', icon: 'brain', color: '#8b5cf6' },
        { label: 'Enter Grades', icon: 'clipboardList', color: '#1e40af' },
        { label: 'Generate Schedule', icon: 'calendar', color: '#10b981' },
        { label: 'View Journals', icon: 'notebookPen', color: '#f59e0b' }
    ],
    student: [
        { label: 'View Grades', icon: 'award', color: '#10b981' },
        { label: 'Homework', icon: 'bookCopy', color: '#1e40af' },
        { label: 'My Schedule', icon: 'calendarDays', color: '#8b5cf6' },
        { label: 'Knowledge Base', icon: 'library', color: '#f59e0b' }
    ]
};

// Activity data for each role
const activityData = {
    admin: [
        { id: '1', title: 'New Student Registration', description: '5 new students registered today', time: '30m ago', type: 'info' },
        { id: '2', title: 'System Backup Completed', description: 'Automated backup finished successfully', time: '2h ago', type: 'success' },
        { id: '3', title: 'Teacher Account Approved', description: 'Dr. Smith\'s account has been verified', time: '4h ago', type: 'success' },
        { id: '4', title: 'Low Disk Space Warning', description: 'Server storage at 85% capacity', time: '6h ago', type: 'warning' }
    ],
    teacher: [
        { id: '1', title: 'New Submission', description: '3 students submitted homework', time: '15m ago', type: 'info' },
        { id: '2', title: 'AI Lesson Plan Ready', description: 'Your lesson plan for next week is generated', time: '45m ago', type: 'success' },
        { id: '3', title: 'Grade Deadline', description: 'Final grades due in 2 days', time: '3h ago', type: 'warning' },
        { id: '4', title: 'Parent Meeting Scheduled', description: 'Meeting with Johnson family at 3 PM', time: '5h ago', type: 'info' }
    ],
    student: [
        { id: '1', title: 'New Grade Posted', description: 'Mathematics quiz: 92/100', time: '20m ago', type: 'success' },
        { id: '2', title: 'Homework Reminder', description: 'Physics assignment due tomorrow', time: '1h ago', type: 'warning' },
        { id: '3', title: 'Class Cancelled', description: 'History class on Friday is cancelled', time: '2h ago', type: 'info' },
        { id: '4', title: 'Achievement Unlocked', description: 'Perfect attendance for 30 days!', time: '8h ago', type: 'success' }
    ]
};

// Current state
let currentRole = 'admin';
let isSidebarCollapsed = false;

// DOM Elements
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const navSections = document.getElementById('navSections');
const roleIndicator = document.getElementById('roleIndicator');
const userAvatar = document.getElementById('userAvatar');
const userName = document.getElementById('userName');
const userRole = document.getElementById('userRole');
const welcomeMessage = document.getElementById('welcomeMessage');
const roleDescription = document.getElementById('roleDescription');
const roleBadge = document.getElementById('roleBadge');
const statsGrid = document.getElementById('statsGrid');
const quickActions = document.getElementById('quickActions');
const activityList = document.getElementById('activityList');
const roleButtons = document.querySelectorAll('.role-btn');

// Helper functions
function getWelcomeMessage(name) {
    const hour = new Date().getHours();
    let greeting = 'Good morning';
    if (hour >= 12 && hour < 17) greeting = 'Good afternoon';
    if (hour >= 17) greeting = 'Good evening';
    return `${greeting}, ${name}!`;
}

function getRoleDescription(role) {
    const descriptions = {
        admin: 'Manage students, teachers, and system settings from your administrator dashboard.',
        teacher: 'Manage your classes, grades, and access AI-powered teaching tools.',
        student: 'View your grades, homework, schedule, and track your academic progress.'
    };
    return descriptions[role];
}

function getRoleTitle(role) {
    const titles = {
        admin: 'Administrator Panel',
        teacher: 'Teacher Workspace',
        student: 'Student Portal'
    };
    return titles[role];
}

function getActivityIcon(type) {
    const iconMap = {
        success: 'checkCircle',
        warning: 'alertTriangle',
        error: 'alertCircle',
        info: 'info'
    };
    return iconMap[type] || 'info';
}

function getActivityColor(type) {
    const colorMap = {
        success: '#10b981',
        warning: '#f59e0b',
        error: '#ef4444',
        info: '#3b82f6'
    };
    return colorMap[type] || '#3b82f6';
}

// Render functions
function renderNavigation() {
    const navItems = navigationConfig[currentRole];
    navSections.innerHTML = navItems.map(item => `
        <a href="#${item.path}" class="nav-item" data-page="${item.path}">
            ${icons[item.icon]}
            <span class="nav-label">${item.label}</span>
        </a>
    `).join('');

    // Add click handlers to nav items
    document.querySelectorAll('.nav-item[data-page]').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const page = item.getAttribute('data-page');
            navigateToPage(page);
            
            // Update active state
            document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');
        });
    });
}

function renderStats() {
    const stats = statsData[currentRole];
    statsGrid.innerHTML = stats.map(stat => {
        const trendIcon = stat.changeType === 'positive' ? icons.trendingUp : 
                         stat.changeType === 'negative' ? icons.trendingDown : 
                         icons.moreHorizontal;
        
        return `
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">${stat.title}</span>
                    <div class="stat-icon">
                        ${icons[stat.icon]}
                    </div>
                </div>
                <div class="stat-value">${stat.value}</div>
                ${stat.change ? `
                    <div class="stat-change ${stat.changeType}">
                        ${trendIcon}
                        <span>${stat.change}</span>
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');
}

function renderQuickActions() {
    const actions = quickActionsData[currentRole];
    quickActions.innerHTML = actions.map(action => `
        <button class="quick-action-btn" style="--action-color: ${action.color}">
            <div class="action-icon" style="background-color: ${action.color}15; color: ${action.color}">
                ${icons[action.icon]}
            </div>
            <span>${action.label}</span>
        </button>
    `).join('');
}

function renderActivities() {
    const activities = activityData[currentRole];
    activityList.innerHTML = activities.map(activity => {
        const icon = getActivityIcon(activity.type);
        const color = getActivityColor(activity.type);
        
        return `
            <div class="activity-item">
                <div class="activity-icon" style="color: ${color}">
                    ${icons[icon]}
                </div>
                <div class="activity-content">
                    <div class="activity-title">${activity.title}</div>
                    <div class="activity-description">${activity.description}</div>
                </div>
                <div class="activity-time">${activity.time}</div>
            </div>
        `;
    }).join('');
}

function updateUserInfo() {
    const user = users[currentRole];
    
    userAvatar.textContent = user.avatar;
    userAvatar.className = `avatar ${currentRole}`;
    userName.textContent = user.name;
    userRole.textContent = getRoleTitle(currentRole).replace(' Panel', '').replace(' Workspace', '').replace(' Portal', '');
    
    welcomeMessage.textContent = getWelcomeMessage(user.name);
    roleDescription.textContent = getRoleDescription(currentRole);
    roleBadge.textContent = getRoleTitle(currentRole).replace(' Panel', ' View').replace(' Workspace', ' View').replace(' Portal', ' View');
    roleBadge.className = `role-badge ${currentRole}`;
    roleIndicator.textContent = getRoleTitle(currentRole);
}

function updateRoleButtons() {
    roleButtons.forEach(btn => {
        btn.classList.toggle('active', btn.dataset.role === currentRole);
    });
}

function navigateToPage(pageId) {
    // Hide all pages
    document.querySelectorAll('.page').forEach(page => {
        page.classList.remove('active');
    });
    
    // Show target page
    const targetPage = document.getElementById(pageId);
    if (targetPage) {
        targetPage.classList.add('active');
    }
}

function switchRole(role) {
    currentRole = role;
    
    updateUserInfo();
    updateRoleButtons();
    renderNavigation();
    renderStats();
    renderQuickActions();
    renderActivities();
    
    // Navigate back to dashboard
    navigateToPage('dashboard');
    document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
    document.querySelector('.nav-item[data-page="dashboard"]').classList.add('active');
}

// Event listeners
sidebarToggle.addEventListener('click', () => {
    isSidebarCollapsed = !isSidebarCollapsed;
    sidebar.classList.toggle('collapsed', isSidebarCollapsed);
});

roleButtons.forEach(btn => {
    btn.addEventListener('click', () => {
        switchRole(btn.dataset.role);
    });
});

// Dashboard nav item click
document.querySelector('.nav-item[data-page="dashboard"]').addEventListener('click', (e) => {
    e.preventDefault();
    navigateToPage('dashboard');
    document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
    e.currentTarget.classList.add('active');
});

// Initialize
function init() {
    renderNavigation();
    renderStats();
    renderQuickActions();
    renderActivities();
    updateUserInfo();
    updateRoleButtons();
}

// Start the app
init();
