<?php
/**
 * Assignments, head of department view (own department only, BR7).
 */
require_once __DIR__ . '/../includes/auth.php';
$user = require_role(['head']);
require __DIR__ . '/../includes/views/assignments_list.php';
