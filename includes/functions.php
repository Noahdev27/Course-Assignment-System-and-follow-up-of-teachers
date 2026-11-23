<?php
/**
 * Shared functions: database (MySQLi), security, display and business calculations.
 * This file is included by auth.php, itself included at the top of every page.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

require_once __DIR__ . '/../config/db.php';

/* ---------------------------------------------------------------------
 * Base URL of the project (works whatever the folder name)
 * ------------------------------------------------------------------- */
if (APP_BASE_URL !== '') {
    define('BASE_URL', rtrim(APP_BASE_URL, '/') . '/');
} else {
    $root = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    $docRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $base = ($docRoot !== '' && stripos($root, $docRoot) === 0) ? substr($root, strlen($docRoot)) : '';
    define('BASE_URL', rtrim($base, '/') . '/');
}

/* ---------------------------------------------------------------------
 * Value lists
 * ------------------------------------------------------------------- */
const GRADES = ['Assistant Lecturer', 'Lecturer', 'Associate Professor', 'Professor'];
const TYPES = ['CM' => 'Lecture', 'TD' => 'Tutorial', 'TP' => 'Lab'];
const LEVELS = ['L1', 'L2', 'L3', 'M1', 'M2'];
const ROLES = [
    'admin' => 'Administrator',
    'head' => 'Head of department',
    'teacher' => 'Teacher',
    'accountant' => 'Accountant',
];
// Default maximum workload (hours per year) by academic rank, used for new sign-ups
const RANK_MAX_LOAD = [
    'Assistant Lecturer' => 200,
    'Lecturer' => 180,
    'Associate Professor' => 160,
    'Professor' => 150,
];
const PAYMENT_METHODS = [
    'cash' => 'Cash',
    'bank_transfer' => 'Bank transfer',
    'mobile_money' => 'Mobile money',
    'cheque' => 'Cheque',
];

/* ---------------------------------------------------------------------
 * Database access: always MySQLi prepared statements
 * $types follows the bind_param convention: i = integer, d = decimal, s = string
 * ------------------------------------------------------------------- */
function db(): mysqli
{
    global $db;
    return $db;
}

function db_run(string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/** All rows of a SELECT, as associative arrays. */
function db_all(string $sql, string $types = '', array $params = []): array
{
    $stmt = db_run($sql, $types, $params);
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

/** First row of a SELECT, or null. */
function db_one(string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_all($sql, $types, $params);
    return $rows[0] ?? null;
}

/** First column of the first row of a SELECT. */
function db_value(string $sql, string $types = '', array $params = [])
{
    $row = db_one($sql, $types, $params);
    return $row === null ? null : array_values($row)[0];
}

/** INSERT / UPDATE / DELETE: returns the number of affected rows. */
function db_exec(string $sql, string $types = '', array $params = []): int
{
    $stmt = db_run($sql, $types, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

function db_insert_id(): int
{
    return (int) db()->insert_id;
}

/* ---------------------------------------------------------------------
 * Display
 * ------------------------------------------------------------------- */
/** Escapes all displayed data (XSS protection). */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function money($amount): string
{
    return number_format((float) $amount, 0, '.', ',') . ' ' . APP_CURRENCY;
}

function hours($value): string
{
    $value = (float) $value;
    $decimals = (abs($value - round($value)) < 0.001) ? 0 : 1;
    return number_format($value, $decimals, '.', ',') . ' h';
}

function format_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $time = strtotime($date);
    return $time ? date('d/m/Y', $time) : '—';
}

function time_short(?string $time): string
{
    return $time ? substr($time, 0, 5) : '';
}

function full_name(array $row, string $prefix = ''): string
{
    return trim(($row[$prefix . 'last_name'] ?? '') . ' ' . ($row[$prefix . 'first_name'] ?? ''));
}

function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Reloads the current page (after a successful or refused POST). */
function redirect_self(): void
{
    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? url()));
    exit;
}

function progress_bar(float $value, float $max, bool $small = false): string
{
    $percent = $max > 0 ? min(100, round($value / $max * 100)) : 0;
    $over = $max > 0 && $value > $max;
    $class = $over ? 'bg-danger' : ($percent >= 100 ? 'bg-success' : 'bg-primary');
    $height = $small ? '6px' : '10px';
    return '<div class="progress" role="progressbar" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100" style="height:' . $height . '">'
        . '<div class="progress-bar ' . $class . '" style="width:' . $percent . '%"></div></div>';
}

function session_badge(string $status): string
{
    $map = [
        'declared' => ['Waiting for approval', 'text-bg-warning', 'hourglass-split'],
        'approved' => ['Approved', 'text-bg-success', 'check-circle-fill'],
        'rejected' => ['Rejected', 'text-bg-danger', 'x-circle-fill'],
    ];
    [$label, $class, $iconName] = $map[$status] ?? [$status, 'text-bg-secondary', 'question-circle'];
    return '<span class="badge status-badge ' . $class . '">' . icon($iconName) . ' ' . e($label) . '</span>';
}

/** Bootstrap Icons glyph (decorative: hidden from screen readers). */
function icon(string $name, string $extraClass = ''): string
{
    return '<i class="bi bi-' . e($name) . ($extraClass !== '' ? ' ' . e($extraClass) : '') . '" aria-hidden="true"></i>';
}

/** Teaching type with its meaning in plain words: “CM · Lecture”. */
function type_badge(string $type): string
{
    $label = TYPES[$type] ?? $type;
    return '<span class="badge type-badge type-' . e(strtolower($type)) . '" title="' . e($label) . '">'
        . e($type) . ' <span class="type-label">· ' . e($label) . '</span></span>';
}

/**
 * Big clickable tile for the main actions of a page.
 * $variant: '' (white), 'main' (filled), 'amber', 'blue' or 'pink'
 */
function action_tile(string $path, string $iconName, string $title, string $text, string $variant = '', ?int $count = null): string
{
    $countHtml = $count ? '<span class="tile-count" title="' . $count . ' waiting">' . $count . '</span>' : '';
    return '<a class="action-tile' . ($variant !== '' ? ' tile-' . e($variant) : '') . '" href="' . e(url($path)) . '">'
        . '<span class="tile-icon">' . icon($iconName) . $countHtml . '</span>'
        . '<span class="tile-text"><strong>' . e($title) . '</strong><small>' . e($text) . '</small></span>'
        . '<span class="tile-arrow">' . icon('chevron-right') . '</span></a>';
}

/**
 * “How does it work?” box with numbered steps. $steps: [[title, explanation], ...]
 * Open by default the first time; the user can fold it.
 */
function how_it_works(array $steps, string $title = 'How does it work?', bool $open = false): string
{
    $html = '<details class="how-it-works no-print"' . ($open ? ' open' : '') . '><summary>' . icon('lightbulb', 'me-1') . e($title) . '</summary><ol class="steps">';
    foreach ($steps as [$stepTitle, $stepText]) {
        $html .= '<li><div><strong>' . e($stepTitle) . '</strong><span>' . e($stepText) . '</span></div></li>';
    }
    return $html . '</ol></details>';
}

/** “Good morning”, “Good afternoon” or “Good evening”, depending on the time. */
function greeting(): string
{
    $hour = (int) date('G');
    if ($hour < 12) {
        return 'Good morning';
    }
    return $hour < 18 ? 'Good afternoon' : 'Good evening';
}

function payment_status(float $due, float $balance): array
{
    if ($due <= 0) {
        return ['Nothing to pay', 'text-bg-light border'];
    }
    if ($balance <= 0) {
        return ['Paid', 'text-bg-success'];
    }
    if ($balance >= $due) {
        return ['Unpaid', 'text-bg-danger'];
    }
    return ['Partially paid', 'text-bg-warning'];
}

/* ---------------------------------------------------------------------
 * Flash messages (shown once, after a redirect)
 * ------------------------------------------------------------------- */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ---------------------------------------------------------------------
 * Forms and CSRF protection
 * ------------------------------------------------------------------- */
function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, $default = '')
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function get(string $key, $default = '')
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Call at the start of every POST handler. */
function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        flash('danger', 'The form has expired. Please try again.');
        redirect_self();
    }
}

function selected($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function valid_time(string $time): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);
}

/* ---------------------------------------------------------------------
 * Logged-in user, active year, activity log
 * ------------------------------------------------------------------- */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $row = db_one(
            'SELECT u.*, d.name AS department_name, d.code AS department_code
             FROM users u LEFT JOIN departments d ON d.id = u.department_id
             WHERE u.id = ?',
            'i',
            [(int) $_SESSION['user_id']]
        );
        // BR2: a deactivated account loses access immediately
        if ($row && (int) $row['is_active'] === 1) {
            $user = $row;
        } else {
            unset($_SESSION['user_id']);
        }
    }
    return $user;
}

function is_teacher_role(string $role): bool
{
    return $role === 'teacher' || $role === 'head';
}

function dashboard_path(string $role): string
{
    $paths = [
        'admin' => 'admin/dashboard.php',
        'head' => 'head/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'accountant' => 'accountant/dashboard.php',
    ];
    return $paths[$role] ?? 'index.php';
}

function active_year(): ?array
{
    static $year = false;
    if ($year === false) {
        $year = db_one('SELECT * FROM academic_years WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
    }
    return $year;
}

/**
 * SQL condition that is true for an account request still waiting for the administrator:
 * created from the sign-up page (log entry “Sign-up request”) and not activated yet.
 */
function pending_signup_sql(string $alias = 'u'): string
{
    return "($alias.is_active = 0 AND EXISTS (SELECT 1 FROM activity_log sl
             WHERE sl.user_id = $alias.id AND sl.action = 'Sign-up request'))";
}

/** Account requests waiting for approval, oldest first. */
function pending_signups(): array
{
    return db_all(
        'SELECT u.*, d.code AS department_code, d.name AS department_name FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         WHERE ' . pending_signup_sql('u') . ' ORDER BY u.created_at, u.id'
    );
}

function is_pending_signup(int $userId): bool
{
    return (bool) db_value('SELECT COUNT(*) FROM users u WHERE u.id = ? AND ' . pending_signup_sql('u'), 'i', [$userId]);
}

/** BR10: records who did what and when. */
function log_action(string $action, string $details = ''): void
{
    $user = current_user();
    db_exec(
        'INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)',
        'iss',
        [$user['id'] ?? null, $action, mb_substr($details, 0, 500)]
    );
}

/* ---------------------------------------------------------------------
 * Business calculations
 * ------------------------------------------------------------------- */

/** Hours of one type (CM, TD or TP) planned in a course. */
function course_type_hours(array $course, string $type): float
{
    return (float) ($course[strtolower($type) . '_hours'] ?? 0);
}

/** Hours already assigned on a course, for a type and a year. */
function course_assigned_hours(int $courseId, int $yearId, string $type, int $excludeId = 0): float
{
    return (float) db_value(
        'SELECT COALESCE(SUM(hours), 0) FROM assignments
         WHERE course_id = ? AND year_id = ? AND type = ? AND id <> ?',
        'iisi',
        [$courseId, $yearId, $type, $excludeId]
    );
}

/** A teacher's workload for a year (sum of assigned hours). */
function teacher_load(int $teacherId, int $yearId, int $excludeId = 0): float
{
    return (float) db_value(
        'SELECT COALESCE(SUM(hours), 0) FROM assignments
         WHERE teacher_id = ? AND year_id = ? AND id <> ?',
        'iii',
        [$teacherId, $yearId, $excludeId]
    );
}

/** Hours of an assignment's sessions. $validatedOnly = false also counts pending sessions. */
function assignment_session_hours(int $assignmentId, bool $validatedOnly, int $excludeSessionId = 0): float
{
    $statusSql = $validatedOnly ? "status = 'approved'" : "status <> 'rejected'";
    return (float) db_value(
        "SELECT COALESCE(SUM(duration), 0) FROM class_sessions
         WHERE assignment_id = ? AND $statusSql AND id <> ?",
        'ii',
        [$assignmentId, $excludeSessionId]
    );
}

/**
 * Overview of the teachers for a year: workload, hours taught, amounts.
 * Amount due = already paid + value of approved sessions not yet paid (BR13).
 */
function teachers_overview(int $yearId, ?int $departmentId = null, ?int $teacherId = null): array
{
    $sql = "SELECT u.id, u.last_name, u.first_name, u.email, u.academic_rank, u.role, u.max_load, u.is_active,
                   d.code AS department_code, d.name AS department_name,
                   (SELECT COALESCE(SUM(a.hours), 0) FROM assignments a
                     WHERE a.teacher_id = u.id AND a.year_id = ?) AS teaching_load,
                   (SELECT COALESCE(SUM(s.duration), 0) FROM class_sessions s
                     JOIN assignments a ON a.id = s.assignment_id
                     WHERE a.teacher_id = u.id AND a.year_id = ? AND s.status = 'approved') AS approved_hours,
                   (SELECT COALESCE(SUM(s.duration), 0) FROM class_sessions s
                     JOIN assignments a ON a.id = s.assignment_id
                     WHERE a.teacher_id = u.id AND a.year_id = ? AND s.status = 'declared') AS pending_hours,
                   (SELECT COALESCE(SUM(s.duration * COALESCE(t.rate_per_hour, 0)), 0) FROM class_sessions s
                     JOIN assignments a ON a.id = s.assignment_id
                     JOIN users u2 ON u2.id = a.teacher_id
                     LEFT JOIN hourly_rates t ON t.academic_rank = u2.academic_rank AND t.type = a.type
                     WHERE a.teacher_id = u.id AND a.year_id = ? AND s.status = 'approved'
                       AND s.payment_id IS NULL) AS balance,
                   (SELECT COALESCE(SUM(p.amount), 0) FROM payments p
                     WHERE p.teacher_id = u.id AND p.year_id = ?) AS paid
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE u.role IN ('teacher', 'head') AND NOT " . pending_signup_sql('u');
    $types = 'iiiii';
    $params = [$yearId, $yearId, $yearId, $yearId, $yearId];
    if ($departmentId) {
        $sql .= ' AND u.department_id = ?';
        $types .= 'i';
        $params[] = $departmentId;
    }
    if ($teacherId) {
        $sql .= ' AND u.id = ?';
        $types .= 'i';
        $params[] = $teacherId;
    }
    $sql .= ' ORDER BY u.last_name, u.first_name';

    $rows = db_all($sql, $types, $params);
    foreach ($rows as &$row) {
        $row['due'] = (float) $row['paid'] + (float) $row['balance'];
        [$row['status_label'], $row['status_class']] = payment_status($row['due'], (float) $row['balance']);
        $row['overloaded'] = (float) $row['teaching_load'] > (float) $row['max_load'] && (float) $row['max_load'] > 0;
    }
    unset($row);
    return $rows;
}

function teacher_summary(int $teacherId, int $yearId): ?array
{
    $rows = teachers_overview($yearId, null, $teacherId);
    return $rows[0] ?? null;
}

/** Coverage of each active course: planned and assigned hours per type. */
function courses_coverage(int $yearId, ?int $departmentId = null, bool $activeOnly = true): array
{
    $sql = "SELECT c.*, d.code AS department_code, d.name AS department_name,
                   COALESCE(SUM(CASE WHEN a.type = 'CM' THEN a.hours END), 0) AS assigned_cm,
                   COALESCE(SUM(CASE WHEN a.type = 'TD' THEN a.hours END), 0) AS assigned_td,
                   COALESCE(SUM(CASE WHEN a.type = 'TP' THEN a.hours END), 0) AS assigned_tp,
                   COUNT(a.id) AS assignment_count
            FROM courses c
            JOIN departments d ON d.id = c.department_id
            LEFT JOIN assignments a ON a.course_id = c.id AND a.year_id = ?
            WHERE 1 = 1";
    $types = 'i';
    $params = [$yearId];
    if ($activeOnly) {
        $sql .= ' AND c.is_active = 1';
    }
    if ($departmentId) {
        $sql .= ' AND c.department_id = ?';
        $types .= 'i';
        $params[] = $departmentId;
    }
    $sql .= ' GROUP BY c.id ORDER BY c.code';

    $rows = db_all($sql, $types, $params);
    foreach ($rows as &$row) {
        $row['complete'] = (float) $row['assigned_cm'] >= (float) $row['cm_hours']
            && (float) $row['assigned_td'] >= (float) $row['td_hours']
            && (float) $row['assigned_tp'] >= (float) $row['tp_hours'];
    }
    unset($row);
    return $rows;
}

/**
 * Appoints the head of a department (F4) and keeps roles consistent:
 * the previous head becomes a teacher again, the new one takes the “head” role.
 */
function set_department_head(int $departmentId, ?int $userId): void
{
    $department = db_one('SELECT * FROM departments WHERE id = ?', 'i', [$departmentId]);
    if (!$department) {
        return;
    }
    $oldHead = $department['head_id'] ? (int) $department['head_id'] : null;
    if ($oldHead && $oldHead !== $userId) {
        $stillHead = (int) db_value(
            'SELECT COUNT(*) FROM departments WHERE head_id = ? AND id <> ?',
            'ii',
            [$oldHead, $departmentId]
        );
        if ($stillHead === 0) {
            db_exec("UPDATE users SET role = 'teacher' WHERE id = ? AND role = 'head'", 'i', [$oldHead]);
        }
    }
    if ($userId) {
        // A head leads only one department
        db_exec('UPDATE departments SET head_id = NULL WHERE head_id = ? AND id <> ?', 'ii', [$userId, $departmentId]);
        db_exec("UPDATE users SET role = 'head', department_id = ? WHERE id = ?", 'ii', [$departmentId, $userId]);
    }
    db_exec('UPDATE departments SET head_id = ? WHERE id = ?', 'ii', [$userId, $departmentId]);
}

/** Approved, unpaid sessions of a teacher, with their value. */
function unpaid_sessions(int $teacherId, int $yearId): array
{
    return db_all(
        "SELECT s.*, a.type, c.code, c.title,
                COALESCE(t.rate_per_hour, 0) AS rate,
                s.duration * COALESCE(t.rate_per_hour, 0) AS amount
         FROM class_sessions s
         JOIN assignments a ON a.id = s.assignment_id
         JOIN courses c ON c.id = a.course_id
         JOIN users u ON u.id = a.teacher_id
         LEFT JOIN hourly_rates t ON t.academic_rank = u.academic_rank AND t.type = a.type
         WHERE a.teacher_id = ? AND a.year_id = ? AND s.status = 'approved' AND s.payment_id IS NULL
         ORDER BY s.session_date, s.start_time",
        'ii',
        [$teacherId, $yearId]
    );
}

/**
 * Checks an assignment before saving (BR3 to BR7, BR9).
 * $data: course_id, teacher_id, type, hours, force, justification
 * $existing: the assignment being edited, or null when creating
 * Returns the list of errors (empty if everything is correct).
 */
function validate_assignment(array $data, ?array $existing, array $user, array $year): array
{
    $errors = [];
    $excludeId = $existing ? (int) $existing['id'] : 0;

    if ($existing && (int) $existing['year_id'] !== (int) $year['id']) {
        return ['Only assignments of the active year can be edited (BR9).'];
    }

    $course = db_one('SELECT * FROM courses WHERE id = ? AND is_active = 1', 'i', [$data['course_id']]);
    $teacher = db_one(
        "SELECT * FROM users WHERE id = ? AND is_active = 1 AND role IN ('teacher', 'head')",
        'i',
        [$data['teacher_id']]
    );
    if (!$course) {
        $errors[] = 'Choose an active course.';
    }
    if (!$teacher) {
        $errors[] = 'Choose an active teacher.';
    }
    if (!array_key_exists($data['type'], TYPES)) {
        $errors[] = 'The type must be CM, TD or TP.';
    }
    if (!is_numeric($data['hours']) || (float) $data['hours'] <= 0) {
        $errors[] = 'The number of hours must be greater than 0 (BR3).';
    }
    if ($errors) {
        return $errors;
    }
    $hoursValue = (float) $data['hours'];

    // BR7: a head stays within their department
    if ($user['role'] === 'head') {
        if ((int) $course['department_id'] !== (int) $user['department_id']
            || (int) $teacher['department_id'] !== (int) $user['department_id']) {
            $errors[] = 'A head of department only assigns the courses and teachers of their own department (BR7).';
        }
    }

    // BR4: no duplicate course + teacher + type in the year
    $duplicate = db_value(
        'SELECT COUNT(*) FROM assignments
         WHERE course_id = ? AND teacher_id = ? AND year_id = ? AND type = ? AND id <> ?',
        'iiisi',
        [$course['id'], $teacher['id'], $year['id'], $data['type'], $excludeId]
    );
    if ($duplicate > 0) {
        $errors[] = 'This teacher already has this course with this type this year (BR4).';
    }

    // BR5: do not exceed the hours planned for this type
    $planned = course_type_hours($course, $data['type']);
    $already = course_assigned_hours((int) $course['id'], (int) $year['id'], $data['type'], $excludeId);
    if ($planned <= 0) {
        $errors[] = 'This course plans no hours of ' . $data['type'] . '.';
    } elseif ($already + $hoursValue > $planned) {
        $errors[] = sprintf(
            'Only %s of %s are left to assign on this course (%s planned, %s already assigned) (BR5).',
            hours(max(0, $planned - $already)),
            $data['type'],
            hours($planned),
            hours($already)
        );
    }

    // BR12: an assignment cannot go below the hours already declared or taught
    if ($existing) {
        $used = assignment_session_hours((int) $existing['id'], false);
        if ($hoursValue < $used) {
            $errors[] = 'Sessions totalling ' . hours($used) . ' are already declared on this assignment: the number of hours cannot be lower.';
        }
    }

    // BR6: teacher's maximum workload
    $load = teacher_load((int) $teacher['id'], (int) $year['id'], $excludeId);
    $max = (float) $teacher['max_load'];
    if ($max > 0 && $load + $hoursValue > $max) {
        $message = sprintf(
            'This assignment would bring %s\'s workload to %s for a maximum of %s (BR6).',
            full_name($teacher),
            hours($load + $hoursValue),
            hours($max)
        );
        $forced = $user['role'] === 'admin' && !empty($data['force']);
        if (!$forced) {
            $errors[] = $message . ($user['role'] === 'admin' ? ' Tick “Override” and give a reason to proceed anyway.' : '');
        } elseif (trim((string) ($data['justification'] ?? '')) === '') {
            $errors[] = 'A reason is required to override the maximum workload.';
        }
    }

    return $errors;
}
