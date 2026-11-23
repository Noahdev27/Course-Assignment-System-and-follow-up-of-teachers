<?php
/**
 * MySQL connection settings and application settings.
 * By default, XAMPP and WAMP use the “root” user with no password.
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'course_assignment');
define('DB_PORT', 3306);

define('APP_NAME', 'Course Assignment System & Follow-up of Teaching');
define('APP_CURRENCY', 'FCFA');

// Project URL, for example '/course-assignment/'.
// Leave empty: it is detected automatically.
define('APP_BASE_URL', '');

// For the demo, keep false so that raw PHP errors are never shown.
define('APP_DEBUG', false);

ini_set('display_errors', APP_DEBUG ? '1' : '0');
date_default_timezone_set('Africa/Douala');

// MySQLi throws an exception on every SQL error: no error goes unnoticed.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $db->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    exit('<!doctype html><meta charset="utf-8"><title>Database unavailable</title>'
        . '<div style="font-family:sans-serif;max-width:640px;margin:60px auto">'
        . '<h1>Cannot connect to the database</h1>'
        . '<p>Check that MySQL is running in XAMPP or WAMP and that the file '
        . '<code>database/database.sql</code> has been imported in phpMyAdmin.</p></div>');
}
