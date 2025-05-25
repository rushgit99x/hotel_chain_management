<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!file_exists('includes/config.php')) {
    die("Error: includes/config.php not found.");
}
include_once 'includes/config.php';

// Debug: Confirm file is loaded
error_log("functions.php loaded");

// Prevent function redefinition
if (!function_exists('checkAuth')) {
    function checkAuth($role = null) {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
        if ($role && $_SESSION['role'] !== $role) {
            header("Location: index.php");
            exit;
        }
    }
}

if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

if (!function_exists('validateEmail')) {
    function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('isRoomAvailable')) {
    function isRoomAvailable($pdo, $room_id, $check_in, $check_out) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings 
                               WHERE room_id = ? 
                               AND status = 'confirmed' 
                               AND (check_in <= ? AND check_out >= ?)");
        $stmt->execute([$room_id, $check_out, $check_in]);
        return $stmt->fetchColumn() == 0;
    }
}

if (!function_exists('getUserBranch')) {
    function getUserBranch($pdo, $user_id) {
        $stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }
}
?>