<?php
declare(strict_types=1);

require_once __DIR__ . '/login.php';

if (!app_direct_request(__FILE__)) {
    return;
}

app_bootstrap();

$session = app_auth_session();
$userId = (int) ($session['user']['id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $user = mysql_get_user_by_id($userId);

    if (!$user) {
        app_error('Authentication required.', 401, [], ['redirect' => 'login.html']);
    }

    $profile = mongodb_fetch_profile($userId) ?? [
        'user_id' => $userId,
        'name' => '',
        'age' => null,
        'bio' => '',
        'interests' => [],
    ];

    json_response(true, 'Profile loaded.', [
        'user' => [
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'email' => (string) $user['email'],
        ],
        'profile' => [
            'name' => (string) ($profile['name'] ?? ''),
            'age' => $profile['age'] ?? null,
            'bio' => (string) ($profile['bio'] ?? ''),
            'interests' => array_values($profile['interests'] ?? []),
        ],
    ]);
}

if ($method !== 'POST') {
    app_error('Method not allowed.', 405);
}

$input = app_request_data();
$action = app_text($input['action'] ?? '', 20);

if ($action === 'logout') {
    $token = app_cookie_token();

    if ($token !== '') {
        redis_delete_session($token);
    }

    app_clear_cookie();

    json_response(true, 'Logged out successfully.', [
        'redirect' => 'login.html',
    ]);
}

$name = app_text($input['name'] ?? '', 100);
$age = app_int_value($input['age'] ?? null, 1, 120);
$bio = app_text($input['bio'] ?? '', 1000);
$interests = app_list($input['interests'] ?? []);
$errors = [];

if ($name === '') {
    $errors['name'] = 'Name is required.';
}

if ($age === null) {
    $errors['age'] = 'Age must be between 1 and 120.';
}

if ($errors) {
    app_error('Validation failed.', 422, $errors);
}

$profile = mongodb_upsert_profile($userId, [
    'name' => $name,
    'age' => $age,
    'bio' => $bio,
    'interests' => $interests,
]);

json_response(true, 'Profile updated.', [
    'profile' => [
        'name' => (string) ($profile['name'] ?? $name),
        'age' => $profile['age'] ?? $age,
        'bio' => (string) ($profile['bio'] ?? $bio),
        'interests' => array_values($profile['interests'] ?? $interests),
    ],
]);
