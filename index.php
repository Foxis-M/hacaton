<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/language-switcher.php';

// Set language to Russian by default
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'ru';
}

// Require authentication
requireAuth();

// Get current user
$currentUser = getCurrentUser();
if (!$currentUser) {
    logoutUser();
    header('Location: /login.php');
    exit;
}

$userRole = $currentUser['role'];
$userName = $currentUser['first_name'] . ' ' . $currentUser['last_name'];
$userAvatar = $currentUser['avatar'] ?: strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1));

// Get dashboard data based on role
$dashboardData = getDashboardData($userRole, $currentUser['id']);

// Get navigation items based on role
$navItems = getNavigationItems($userRole);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AGKB College - <?php echo getTranslation('Dashboard', 'ru'); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="app">
        <!-- Sidebar Navigation -->
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

            <div class="role-indicator" id="roleIndicator">
                <?php echo ucfirst($userRole); ?> Panel
            </div>

            <nav class="sidebar-nav">
                <a href="/" class="nav-item active">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span class="nav-label"><?php echo getTranslation('Dashboard', 'ru'); ?></span>
                </a>

                <div class="nav-sections">
                    <?php foreach ($navItems as $item): ?>
                        <a href="<?php echo htmlspecialchars($item['path']); ?>" class="nav-item">
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

        <!-- Main Content Area -->
        <div class="main-wrapper">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <div class="search-bar">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        <input type="text" placeholder="Search...">
                    </div>
                </div>

                <div class="header-right">
                    <div class="language-selector">
                        <?php renderLanguageSwitcher(); ?>
                    </div>
                    <button class="notification-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
                            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
                        </svg>
                        <span class="notification-badge">3</span>
                    </button>

                    <div class="user-menu">
                        <div class="avatar <?php echo $userRole; ?>"><?php echo htmlspecialchars($userAvatar); ?></div>
                        <div class="user-info">
                            <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                            <span class="user-role"><?php echo ucfirst($userRole); ?></span>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
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

            <!-- Main Content -->
            <main class="main-content">
                <div class="dashboard">
                    <div class="page-header">
                        <div class="welcome-section">
                            <h1><?php echo getWelcomeMessage($userName); ?></h1>
                            <p class="role-description">
                                <?php echo getRoleDescription($userRole); ?>
                            </p>
                        </div>
                        <div class="role-badge <?php echo $userRole; ?>">
                            <?php echo ucfirst($userRole); ?> View
                        </div>
                    </div>

                    <!-- Stats Grid -->
                    <div class="stats-grid">
                        <?php foreach ($dashboardData['stats'] as $stat): ?>
                            <div class="stat-card">
                                <div class="stat-header">
                                    <span class="stat-title"><?php echo htmlspecialchars($stat['title']); ?></span>
                                    <div class="stat-icon">
                                        <?php echo $stat['icon']; ?>
                                    </div>
                                </div>
                                <div class="stat-value"><?php echo htmlspecialchars($stat['value']); ?></div>
                                <?php if ($stat['change']): ?>
                                    <div class="stat-change <?php echo $stat['changeType']; ?>">
                                        <?php echo $stat['trendIcon']; ?>
                                        <span><?php echo htmlspecialchars($stat['change']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="dashboard-content">
                        <!-- Quick Actions -->
                        <div class="content-section">
                            <h2><?php echo getTranslation('Quick Actions', 'ru'); ?></h2>
                            <div class="quick-actions">
                                <?php foreach ($dashboardData['quickActions'] as $action): ?>
                                    <button class="quick-action-btn" style="--action-color: <?php echo $action['color']; ?>">
                                        <div class="action-icon" style="background-color: <?php echo $action['color']; ?>15; color: <?php echo $action['color']; ?>">
                                            <?php echo $action['icon']; ?>
                                        </div>
                                        <span><?php echo htmlspecialchars($action['label']); ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="content-section">
                            <h2><?php echo getTranslation('Recent Activity', 'ru'); ?></h2>
                            <div class="activity-list">
                                <?php foreach ($dashboardData['activities'] as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon" style="color: <?php echo $activity['color']; ?>">
                                            <?php echo $activity['icon']; ?>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                                            <div class="activity-description"><?php echo htmlspecialchars($activity['description']); ?></div>
                                        </div>
                                        <div class="activity-time"><?php echo htmlspecialchars($activity['time']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        });
    </script>
</body>
</html>
