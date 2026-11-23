<?php
/**
 * Assignments, administrator view (all departments).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['admin']);
require __DIR__ . '/../includes/views/assignments_list.php';
