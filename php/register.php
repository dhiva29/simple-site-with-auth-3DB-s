<?php
declare(strict_types=1);

require_once __DIR__ . '/login.php';

if (!app_direct_request(__FILE__)) {
    return;
}

app_bootstrap();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    app_error('Method not allowed.', 405);
}

$input = app_request_data();
$username = app_text($input['username'] ?? '', 50);
$email = app_email($input['email'] ?? '');
$password = (string) ($input['password'] ?? '');
$confirmPassword = (string) ($input['confirm_password'] ?? '');
$errors = [];

if ($username === '') {
    $errors['username'] = 'Username is required.';
} elseif (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
    $errors['username'] = 'Username must be 3 to 50 characters and contain only letters, numbers, dots, underscores, or hyphens.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'A valid email is required.';
}

if (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}

if ($confirmPassword === '' || $password !== $confirmPassword) {
    $errors['confirm_password'] = 'Passwords do not match.';
}

if ($errors) {
    app_error('Validation failed.', 422, $errors);
}

if (mysql_find_user_by_username($username)) {
    app_error('Username already exists.', 409, ['username' => 'Username already exists.']);
}

if (mysql_find_user_by_email($email)) {
    app_error('Email already exists.', 409, ['email' => 'Email already exists.']);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$userId = mysql_create_user($username, $email, $passwordHash);

mongodb_upsert_profile($userId, [
    'name' => '',
    'age' => null,
    'bio' => '',
    'interests' => [],
]);

json_response(true, 'Registration successful.', [
    'redirect' => 'login.html',
    'user' => [
        'id' => $userId,
        'username' => $username,
        'email' => $email,
    ],
]);
