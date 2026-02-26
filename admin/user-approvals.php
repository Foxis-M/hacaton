<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/functions.php';

// Require authentication and admin role
requireAuth();
$currentUser = getCurrentUser();
if (!$currentUser || $currentUser['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    header('Location: /?error=unauthorized');
    exit;
}

$message = '';
$messageType = '';

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($userId && in_array($action, ['approve', 'reject'])) {
        try {
            $pdo = getDBConnection();
            
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE users SET is_active = TRUE WHERE id = ?");
                $stmt->execute([$userId]);
                $message = 'User approved successfully.';
                $messageType = 'success';
            } else {
                // Reject - delete user and related records
                $stmt = $pdo->prepare("DELETE FROM students WHERE user_id = ?");
                $stmt->execute([$userId]);
                $stmt = $pdo->prepare("DELETE FROM teachers WHERE user_id = ?");
                $stmt->execute([$userId]);
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $message = 'User rejected and removed.';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get pending users
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT id, username, email, role, first_name, last_name, created_at 
                         FROM users 
                         WHERE is_active = FALSE 
                         ORDER BY created_at DESC");
    $pendingUsers = $stmt->fetchAll();
} catch (PDOException $e) {
    $pendingUsers = [];
    $message = 'Error loading users: ' . $e->getMessage();
    $messageType = 'error';
}

$userRole = $currentUser['role'];
$userName = $currentUser['first_name'] . ' ' . $currentUser['last_name'];
$userAvatar = $currentUser['avatar'] ?: strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1));
$navItems = getNavigationItems($userRole);
$pageTitle = 'User Approvals';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - AGKB College</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .page-content { padding: 24px; }
        .page-header { margin-bottom: 24px; }
        .page-header h1 { font-size: 28px; color: var(--gray-800); margin-bottom: 8px; }
        .page-header p { color: var(--gray-500); }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        
        .users-table {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .users-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th,
        .users-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .users-table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .users-table td {
            font-size: 14px;
            color: var(--gray-800);
        }
        
        .users-table tr:hover {
            background: var(--gray-50);
        }
        
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .role-badge.student { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .role-badge.teacher { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-approve {
            background: #10b981;
            color: white;
        }
        .btn-approve:hover {
            background: #059669;
        }
        
        .btn-reject {
            background: #ef4444;
            color: white;
        }
        .btn-reject:hover {
            background: #dc2626;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-500);
        }
        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--gray-700);
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
                    <div class="page-header">
                        <h1>Pending User Approvals</h1>
                        <p>Review and approve new user registrations</p>
                    </div>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>
                    
                    <?php if (empty($pendingUsers)): ?>
                        <div class="users-table">
                            <div class="empty-state">
                                <h3>No pending approvals</h3>
                                <p>All user registrations have been processed.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="users-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingUsers as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><span class="role-badge <?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <form method="POST" class="actions">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-approve">Approve</button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-reject">Reject</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
