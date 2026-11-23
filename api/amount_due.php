<?php
/**
 * JSON API: amount due, paid and balance of a teacher for the active year (called with fetch()).
 * GET teacher_id. A teacher can only see their own amounts.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}
$teacherId = (int) get('teacher_id');
$allowed = in_array($user['role'], ['admin', 'accountant'], true) || $teacherId === (int) $user['id'];
$year = active_year();
$summary = ($allowed && $year) ? teacher_summary($teacherId, (int) $year['id']) : null;
if (!$summary) {
    http_response_code(404);
    echo json_encode(['error' => 'Teacher not found']);
    exit;
}

echo json_encode([
    'teacher' => full_name($summary),
    'approved_hours' => (float) $summary['approved_hours'],
    'due' => (float) $summary['due'],
    'paid' => (float) $summary['paid'],
    'balance' => (float) $summary['balance'],
    'status' => $summary['status_label'],
]);
