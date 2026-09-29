<?php
// Database connection settings.
// Adjust these if your local MySQL user/password differ.
$DB_HOST = '127.0.0.1';
$DB_NAME = 'jerry_database';
$DB_USER = 'root';
$DB_PASS = '';

// Login credentials. Only the password hash is stored, never the plain
// password. Generate a new hash with password_hash('<password>', PASSWORD_DEFAULT).
const AUTH_EMAIL = 'jerrysclocks@yahoo.com';
const AUTH_PASSWORD_HASH = '$2y$10$qyy2o0JcPNwO4qkJ7l7xQePfYjepViyOe20bas5EOYmkz4Nehs8f2';

function get_db_connection(): mysqli
{
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;

    $mysqli = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

    if (!$mysqli) {
        die('Database connection failed: ' . mysqli_connect_error());
    }

    return $mysqli;
}
