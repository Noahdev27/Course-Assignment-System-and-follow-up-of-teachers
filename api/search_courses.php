<?php
/**
 * JSON API: course search by code or title (called with fetch()).
 * GET q. A head of department only receives the courses of their department.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user || !in_array($user['role'], ['admin', 'head'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$query = '%' . mb_substr(get('q'), 0, 50) . '%';
$sql = 'SELECT id, code, title, level, semester FROM courses WHERE is_active = 1 AND (code LIKE ? OR title LIKE ?)';
$types = 'ss';
$params = [$query, $query];
if ($user['role'] === 'head') {
    $sql .= ' AND department_id = ?';
    $types .= 'i';
    $params[] = (int) $user['department_id'];
}
$sql .= ' ORDER BY code LIMIT 50';

echo json_encode(db_all($sql, $types, $params));
