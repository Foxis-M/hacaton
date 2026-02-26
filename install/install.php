<?php
require_once __DIR__ . '/config/database.php';

try {
    initDatabase();
    echo "<h1>✅ Database installed successfully!</h1>";
    echo "<p>All tables have been created.</p>";
    echo "<p><a href='/register.php'>Go to registration</a></p>";
    echo "<p><strong>Delete this file (install.php) after installation for security!</strong></p>";
} catch (Exception $e) {
    echo "<h1>❌ Error</h1>";
    echo "<p>" . $e->getMessage() . "</p>";
}
