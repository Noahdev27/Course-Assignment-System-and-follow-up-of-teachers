<?php
/**
 * JSON API: a teacher's workload before and after an assignment (called with fetch()).
 * GET teacher_id, hours, exclude (id of the assignment being edited)
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user || !in_array($user['role'], ['admin', 'head'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}
$year = active_year();
$teacher = db_one(
    "SELECT id, department_id, max_load FROM users WHERE id = ? AND role IN ('teacher', 'head')",
    'i',
    [(int) get('teacher_id')]
);
if (!$year || !$teacher || ($user['role'] === 'head' && (int) $teacher['department_id'] !== (int) $user['department_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Teacher not found']);
    exit;
}

$hours = max(0, (float) get('hours', '0'));
$load = teacher_load((int) $teacher['id'], (int) $year['id'], (int) get('exclude', '0'));
$max = (float) $teacher['max_load'];

echo json_encode([
    'teaching_load' => $load,
    'max_load' => $max,
    'after' => $load + $hours,
    'exceeds' => $max > 0 && $load + $hours > $max,
]);
