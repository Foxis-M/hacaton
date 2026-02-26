<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/translations.php';

// Enable CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $language = $input['language'] ?? null;
    
    if ($language && in_array($language, ['ru', 'en'])) {
        $_SESSION['language'] = $language;
        echo json_encode(['success' => true, 'message' => 'Language updated']);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid language']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>