<?php
// ==========================================
// UniFind Smart - Core Configuration & Helpers
// Marwadi University Lost & Found System
// Procedural PHP 8.2 & MySQLi Prepared Statements
// ==========================================

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'unifind_smart');

// Start Session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection using MySQLi
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Database Connection Failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function get_csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function is_valid_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Dynamic Base URL Helper
function base_url($path = '') {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $dir = str_replace('\\', '/', dirname($scriptName));
    
    if (basename($dir) === 'student' || basename($dir) === 'admin') {
        $dir = str_replace('\\', '/', dirname($dir));
    }
    
    $dir = rtrim($dir, '/\\');
    $cleanPath = ltrim($path, '/\\');
    
    return ($dir === '' || $dir === '/') ? '/' . $cleanPath : $dir . '/' . $cleanPath;
}

// Authentication & Guard Functions
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_current_role() {
    return $_SESSION['role'] ?? 'student';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . base_url('index.php'));
        exit();
    }
}

function require_role($roles) {
    require_login();
    $roles = (array) $roles;
    if (!in_array(get_current_role(), $roles, true)) {
        if (get_current_role() === 'admin' || get_current_role() === 'security') {
            header('Location: ' . base_url('admin/dashboard.php'));
        } else {
            header('Location: ' . base_url('student/dashboard.php'));
        }
        exit();
    }
}

// Activity Logging
function log_activity($userId, $action, $details = '') {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $conn->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('isss', $userId, $action, $details, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// Notification Helper
function create_notification($userId, $title, $message) {
    global $conn;
    $stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('iss', $userId, $title, $message);
        $stmt->execute();
        $stmt->close();
    }
}

function get_unread_notifications_count($userId) {
    global $conn;
    if (!$userId) return 0;
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (int) ($res['total'] ?? 0);
    }
    return 0;
}

// Gamification System & Badge Calculator
function add_reward_points($userId, $points) {
    global $conn;
    if (!$userId || $points <= 0) return;

    $stmt = $conn->prepare('UPDATE users SET reward_points = reward_points + ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $points, $userId);
        $stmt->execute();
        $stmt->close();
    }

    // Fetch updated points to calculate badge
    $q = $conn->prepare('SELECT reward_points FROM users WHERE id = ?');
    if ($q) {
        $q->bind_param('i', $userId);
        $q->execute();
        $u = $q->get_result()->fetch_assoc();
        $q->close();
        $pts = (int) ($u['reward_points'] ?? 0);

        $badge = 'Novice Finder';
        if ($pts >= 500) $badge = 'Legendary Champion';
        elseif ($pts >= 250) $badge = 'Security Guardian';
        elseif ($pts >= 100) $badge = 'Active Detective';
        elseif ($pts >= 50) $badge = 'Helpful Scout';

        $bStmt = $conn->prepare('UPDATE users SET badge = ? WHERE id = ?');
        if ($bStmt) {
            $bStmt->bind_param('si', $badge, $userId);
            $bStmt->execute();
            $bStmt->close();
        }

        if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$userId) {
            $_SESSION['reward_points'] = $pts;
            $_SESSION['badge'] = $badge;
        }
    }
}

// Secure File Upload Handler (Strict 2MB Limit & MIME Check)
function handle_file_upload($fileField) {
    if (!isset($_FILES[$fileField]) || $_FILES[$fileField]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$fileField];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMimes, true)) {
        return false;
    }

    // Strict 2MB Limit as specified in requirements
    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }

    $ext = $mime === 'image/jpeg' ? 'jpg' : ($mime === 'image/png' ? 'png' : 'webp');
    // Generate unique random filename
    $filename = 'item_' . date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    
    $targetDir = __DIR__ . '/uploads/';
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $targetPath = $targetDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/' . $filename;
    }
    return false;
}
