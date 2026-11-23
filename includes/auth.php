<?php
/**
 * Access control. Include at the top of every protected page:
 *   require_once __DIR__ . '/../includes/auth.php';
 *   require_role(['admin']);
 */

require_once __DIR__ . '/functions.php';

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Please log in to continue.');
        redirect('index.php');
    }
    return $user;
}

/** Checks the role on the server: hiding a button is not enough. */
function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        forbidden();
    }
    return $user;
}

function forbidden(string $message = 'You do not have access to this page.'): void
{
    http_response_code(403);
    $page_title = 'Access denied';
    require __DIR__ . '/header.php';
    echo '<div class="alert alert-danger mt-4"><h1 class="h5">Access denied</h1><p class="mb-0">' . e($message) . '</p></div>';
    require __DIR__ . '/footer.php';
    exit;
}

/** Stops the page with a message if no academic year is active. */
function require_active_year(): array
{
    $year = active_year();
    if (!$year) {
        $user = current_user();
        $page_title = 'No active year';
        require __DIR__ . '/header.php';
        echo '<div class="alert alert-warning mt-4">No academic year is active.';
        if ($user && $user['role'] === 'admin') {
            echo ' <a href="' . e(url('admin/years.php')) . '" class="alert-link">Set the active year</a>.';
        } else {
            echo ' Contact the administrator.';
        }
        echo '</div>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $year;
}
