<?php
// Session-based auth guard. Include this before any output on pages that
// require a logged-in user.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in(): bool
{
    return !empty($_SESSION['logged_in']);
}

// Redirects to login.php if there is no valid session. Call this at the
// top of any page that must be protected.
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}
