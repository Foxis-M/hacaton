<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'db');
define('DB_USER', 'root');
define('DB_PASS', '1234');
define('DB_CHARSET', 'utf8mb4');

// Create database connection
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Initialize database and tables
function initDatabase() {
    try {
        // Connect without database to create it
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE " . DB_NAME);
        
        // Create users table
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            avatar VARCHAR(255) DEFAULT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            last_login DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_role (role),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create students table
        $pdo->exec("CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            student_id VARCHAR(20) UNIQUE NOT NULL,
            grade_level INT NOT NULL,
            major VARCHAR(100) DEFAULT NULL,
            gpa DECIMAL(3,2) DEFAULT 0.00,
            enrollment_date DATE NOT NULL,
            graduation_date DATE DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_student_id (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create teachers table
        $pdo->exec("CREATE TABLE IF NOT EXISTS teachers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            teacher_id VARCHAR(20) UNIQUE NOT NULL,
            department VARCHAR(100) NOT NULL,
            position VARCHAR(50) NOT NULL,
            hire_date DATE NOT NULL,
            specialization VARCHAR(255) DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_teacher_id (teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create courses table (professional titles/professions)
        $pdo->exec("CREATE TABLE IF NOT EXISTS courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_code VARCHAR(20) UNIQUE NOT NULL,
            course_name VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            credits INT NOT NULL DEFAULT 3,
            department VARCHAR(100) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Create subjects table (academic disciplines: Physics, Math, etc.)
        $pdo->exec("CREATE TABLE IF NOT EXISTS subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subject_code VARCHAR(20) UNIQUE NOT NULL,
            subject_name VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            department VARCHAR(100) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Create groups table (student groups: Group A, Class 101, etc.)
        $pdo->exec("CREATE TABLE IF NOT EXISTS groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_name VARCHAR(100) NOT NULL,
            group_code VARCHAR(20) UNIQUE NOT NULL,
            description TEXT DEFAULT NULL,
            max_students INT DEFAULT 30,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Create group_subjects table (junction: groups can have multiple subjects)
        $pdo->exec("CREATE TABLE IF NOT EXISTS group_subjects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            subject_id INT NOT NULL,
            teacher_id INT DEFAULT NULL,
            semester VARCHAR(20) NOT NULL,
            academic_year INT NOT NULL,
            FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
            UNIQUE KEY unique_group_subject (group_id, subject_id, semester, academic_year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Create classes table (for backward compatibility - redirects to group_subjects)
        $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            teacher_id INT DEFAULT NULL,
            semester VARCHAR(20) NOT NULL,
            academic_year INT NOT NULL,
            schedule VARCHAR(255) DEFAULT NULL,
            room VARCHAR(50) DEFAULT NULL,
            max_students INT DEFAULT 30,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create enrollments table
        $pdo->exec("CREATE TABLE IF NOT EXISTS enrollments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            class_id INT NOT NULL,
            enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('active', 'dropped', 'completed') DEFAULT 'active',
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            UNIQUE KEY unique_enrollment (student_id, class_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create grades table
        $pdo->exec("CREATE TABLE IF NOT EXISTS grades (
            id INT AUTO_INCREMENT PRIMARY KEY,
            enrollment_id INT NOT NULL,
            assignment_name VARCHAR(100) NOT NULL,
            assignment_type ENUM('homework', 'quiz', 'exam', 'project', 'participation') NOT NULL,
            score DECIMAL(5,2) NOT NULL,
            max_score DECIMAL(5,2) NOT NULL DEFAULT 100,
            grade_letter VARCHAR(2) DEFAULT NULL,
            graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            graded_by INT DEFAULT NULL,
            comments TEXT DEFAULT NULL,
            FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
            FOREIGN KEY (graded_by) REFERENCES teachers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create attendance table
        $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            enrollment_id INT NOT NULL,
            date DATE NOT NULL,
            status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
            notes VARCHAR(255) DEFAULT NULL,
            recorded_by INT NOT NULL,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE,
            FOREIGN KEY (recorded_by) REFERENCES teachers(id) ON DELETE CASCADE,
            UNIQUE KEY unique_attendance (enrollment_id, date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create homework/assignments table
        $pdo->exec("CREATE TABLE IF NOT EXISTS assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            title VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            assignment_type ENUM('homework', 'quiz', 'exam', 'project') NOT NULL,
            due_date DATETIME NOT NULL,
            max_score DECIMAL(5,2) DEFAULT 100,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES teachers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create knowledge base articles table
        $pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_base (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            content TEXT NOT NULL,
            category VARCHAR(50) NOT NULL,
            tags VARCHAR(255) DEFAULT NULL,
            author_id INT NOT NULL,
            is_published BOOLEAN DEFAULT FALSE,
            view_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_category (category),
            INDEX idx_published (is_published)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create activity logs table
        $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            action VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create sessions table for persistent login
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(255) UNIQUE NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_token (session_token),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create lesson plans table
        $pdo->exec("CREATE TABLE IF NOT EXISTS lesson_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            topic VARCHAR(255) NOT NULL,
            subject VARCHAR(100) DEFAULT NULL,
            grade_level VARCHAR(50) DEFAULT NULL,
            duration INT DEFAULT 45,
            objectives TEXT DEFAULT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            INDEX idx_teacher (teacher_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create class topics table (for assigning topics to groups)
        $pdo->exec("CREATE TABLE IF NOT EXISTS class_topics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            class_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            due_date DATE DEFAULT NULL,
            difficulty VARCHAR(20) DEFAULT 'medium',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            INDEX idx_teacher (teacher_id),
            INDEX idx_class (class_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Add difficulty column to existing class_topics table (if not exists)
        try {
            $pdo->exec("ALTER TABLE class_topics ADD COLUMN difficulty VARCHAR(20) DEFAULT 'medium'");
        } catch (PDOException $e) {
            // Column already exists, ignore error
        }
        
        // Create group_teachers table (many-to-many: multiple teachers per group)
        $pdo->exec("CREATE TABLE IF NOT EXISTS group_teachers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            teacher_id INT NOT NULL,
            subject_id INT DEFAULT NULL,
            semester VARCHAR(20) NOT NULL,
            academic_year INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES classes(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
            UNIQUE KEY unique_teacher_group_subject (group_id, teacher_id, subject_id, semester, academic_year),
            INDEX idx_group (group_id),
            INDEX idx_teacher (teacher_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create student_tests table (AI generated tests for students)
        $pdo->exec("CREATE TABLE IF NOT EXISTS student_tests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            topic_id INT NOT NULL,
            difficulty VARCHAR(20) DEFAULT 'medium',
            question_count INT DEFAULT 10,
            content TEXT NOT NULL,
            student_answers TEXT DEFAULT NULL,
            score INT DEFAULT NULL,
            analysis TEXT DEFAULT NULL,
            practice_tasks TEXT DEFAULT NULL,
            completed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (topic_id) REFERENCES class_topics(id) ON DELETE CASCADE,
            INDEX idx_student (student_id),
            INDEX idx_topic (topic_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create rooms table for schedule generation
        $pdo->exec("CREATE TABLE IF NOT EXISTS rooms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_number VARCHAR(50) NOT NULL,
            building VARCHAR(100) DEFAULT NULL,
            capacity INT DEFAULT 30,
            room_type VARCHAR(50) DEFAULT 'classroom',
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_room (room_number, building),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Insert default rooms if none exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM rooms");
        if ($stmt->fetchColumn() == 0) {
            $defaultRooms = [
                ['101', 'Main Building', 30, 'classroom'],
                ['102', 'Main Building', 30, 'classroom'],
                ['103', 'Main Building', 30, 'classroom'],
                ['201', 'Main Building', 40, 'lecture_hall'],
                ['202', 'Main Building', 40, 'lecture_hall'],
                ['Lab 1', 'Science Wing', 25, 'lab'],
                ['Lab 2', 'Science Wing', 25, 'lab'],
                ['A101', 'Annex', 35, 'classroom'],
                ['A102', 'Annex', 35, 'classroom']
            ];
            $stmt = $pdo->prepare("INSERT INTO rooms (room_number, building, capacity, room_type) VALUES (?, ?, ?, ?)");
            foreach ($defaultRooms as $room) {
                $stmt->execute($room);
            }
        }
        
        // Create generated_schedules table
        $pdo->exec("CREATE TABLE IF NOT EXISTS generated_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT NOT NULL,
            admin_id INT NOT NULL,
            schedule_data TEXT NOT NULL,
            weeks INT DEFAULT 1,
            max_classes_per_day INT DEFAULT 4,
            first_class_start INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES classes(id) ON DELETE CASCADE,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_group (group_id),
            INDEX idx_admin (admin_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create class_schedules table for actual schedule storage
        $pdo->exec("CREATE TABLE IF NOT EXISTS class_schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            day_of_week VARCHAR(20) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            room VARCHAR(50) DEFAULT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            schedule_type VARCHAR(50) DEFAULT 'lecture',
            week_number INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
            INDEX idx_class (class_id),
            INDEX idx_day_time (day_of_week, start_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Insert default admin user (password: admin123)
        $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, role, first_name, last_name) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@agkb.edu', $adminHash, 'admin', 'System', 'Administrator']);
        
        // Insert sample teacher (password: teacher123)
        $teacherHash = password_hash('teacher123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, role, first_name, last_name) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['teacher1', 'teacher@agkb.edu', $teacherHash, 'teacher', 'John', 'Smith']);
        
        // Insert sample student (password: student123)
        $studentHash = password_hash('student123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, role, first_name, last_name) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['student1', 'student@agkb.edu', $studentHash, 'student', 'Jane', 'Doe']);
        
        return true;
    } catch (PDOException $e) {
        die("Database initialization failed: " . $e->getMessage());
    }
}

// Helper function to log activity
function logActivity($userId, $action, $description = '') {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Note: Database initialization removed - assuming database and tables already exist
// If you need to create tables, run initDatabase() manually once
