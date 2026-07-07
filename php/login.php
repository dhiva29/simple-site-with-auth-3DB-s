<?php
declare(strict_types=1);

if (!function_exists('app_direct_request')) {
    function app_env(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return is_string($value) ? $value : $default;
    }

    function app_bool(string $key, bool $default = false): bool
    {
        $value = app_env($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    function app_int(string $key, int $default): int
    {
        $value = app_env($key);

        if ($value === null || !is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    function app_string(string $key, string $default = ''): string
    {
        return app_env($key, $default) ?? $default;
    }

    function app_bootstrap(): void
    {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: no-referrer');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
    }

    function json_response(bool $success, string $message, array $data = [], int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    function app_error(string $message, int $statusCode = 400, array $errors = [], array $data = []): void
    {
        json_response(false, $message, array_merge(['errors' => $errors], $data), $statusCode);
    }

    function app_request_data(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        $raw = file_get_contents('php://input');

        if (stripos($contentType, 'application/json') !== false && is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (!empty($_POST)) {
            return $_POST;
        }

        if (is_string($raw) && $raw !== '') {
            parse_str($raw, $parsed);

            if (is_array($parsed)) {
                return $parsed;
            }
        }

        return [];
    }

    function app_text(mixed $value, int $maxLength = 255): string
    {
        $text = trim((string) $value);
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength);
        }

        return $text;
    }

    function app_email(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    function app_int_value(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $intValue = (int) $value;

        if ($intValue < $min || $intValue > $max) {
            return null;
        }

        return $intValue;
    }

    function app_list(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/,/', (string) $value) ?: [];
        $clean = [];

        foreach ($items as $item) {
            $entry = app_text($item, 80);

            if ($entry !== '') {
                $clean[] = $entry;
            }
        }

        return array_values(array_unique($clean));
    }

    function app_cookie_name(): string
    {
        return app_string('AUTH_COOKIE_NAME', 'auth_session');
    }

    function app_cookie_ttl(): int
    {
        return max(300, app_int('AUTH_SESSION_TTL', 86400));
    }

    function app_cookie_params(int $ttl): array
    {
        return [
            'expires' => time() + $ttl,
            'path' => app_string('APP_COOKIE_PATH', '/'),
            'domain' => app_string('APP_COOKIE_DOMAIN', ''),
            'secure' => app_bool('APP_COOKIE_SECURE', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => app_string('APP_COOKIE_SAMESITE', 'Lax'),
        ];
    }

    function app_set_cookie(string $token): void
    {
        setcookie(app_cookie_name(), $token, app_cookie_params(app_cookie_ttl()));
    }

    function app_clear_cookie(): void
    {
        setcookie(app_cookie_name(), '', [
            'expires' => time() - 3600,
            'path' => app_string('APP_COOKIE_PATH', '/'),
            'domain' => app_string('APP_COOKIE_DOMAIN', ''),
            'secure' => app_bool('APP_COOKIE_SECURE', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => app_string('APP_COOKIE_SAMESITE', 'Lax'),
        ]);
    }

    function app_mysql(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $host = app_string('MYSQL_HOST', '127.0.0.1');
        $port = app_int('MYSQL_PORT', 3306);
        $database = app_string('MYSQL_DB', 'auth_app');
        $username = app_string('MYSQL_USER', 'root');
        $password = app_string('MYSQL_PASS', '');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        return $pdo;
    }

    function mysql_find_user_by_identifier(string $identifier): ?array
    {
        $statement = app_mysql()->prepare('SELECT id, username, email, password_hash FROM users WHERE username = :identifier OR email = :identifier LIMIT 1');
        $statement->execute(['identifier' => $identifier]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    function mysql_find_user_by_username(string $username): ?array
    {
        $statement = app_mysql()->prepare('SELECT id, username, email, password_hash FROM users WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    function mysql_find_user_by_email(string $email): ?array
    {
        $statement = app_mysql()->prepare('SELECT id, username, email, password_hash FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    function mysql_get_user_by_id(int $userId): ?array
    {
        $statement = app_mysql()->prepare('SELECT id, username, email, password_hash FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch();

        return $row ?: null;
    }

    function mysql_create_user(string $username, string $email, string $passwordHash): int
    {
        $statement = app_mysql()->prepare('INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)');
        $statement->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);

        return (int) app_mysql()->lastInsertId();
    }

    function app_mongo_manager(): MongoDB\Driver\Manager
    {
        static $manager = null;

        if ($manager instanceof MongoDB\Driver\Manager) {
            return $manager;
        }

        $uri = app_string('MONGO_URI', 'mongodb://127.0.0.1:27017');
        $manager = new MongoDB\Driver\Manager($uri);

        return $manager;
    }

    function app_mongo_namespace(): string
    {
        $database = app_string('MONGO_DB', 'auth_app');
        $collection = app_string('MONGO_COLLECTION', 'profiles');

        return $database . '.' . $collection;
    }

    function app_mongo_normalize(mixed $document): ?array
    {
        if ($document === null) {
            return null;
        }

        if (is_object($document)) {
            $document = json_decode(json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);
        }

        if (!is_array($document)) {
            return null;
        }

        return [
            'user_id' => (int) ($document['user_id'] ?? 0),
            'name' => app_text($document['name'] ?? '', 100),
            'age' => isset($document['age']) && is_numeric($document['age']) ? (int) $document['age'] : null,
            'bio' => app_text($document['bio'] ?? '', 1000),
            'interests' => app_list($document['interests'] ?? []),
        ];
    }

    function mongodb_fetch_profile(int $userId): ?array
    {
        $query = new MongoDB\Driver\Query(['user_id' => $userId], ['limit' => 1]);
        $cursor = app_mongo_manager()->executeQuery(app_mongo_namespace(), $query);

        foreach ($cursor as $document) {
            return app_mongo_normalize($document);
        }

        return null;
    }

    function mongodb_upsert_profile(int $userId, array $profile): array
    {
        $bulk = new MongoDB\Driver\BulkWrite();
        $now = new MongoDB\BSON\UTCDateTime((int) round(microtime(true) * 1000));

        $bulk->update(
            ['user_id' => $userId],
            ['$set' => [
                'user_id' => $userId,
                'name' => app_text($profile['name'] ?? '', 100),
                'age' => $profile['age'] ?? null,
                'bio' => app_text($profile['bio'] ?? '', 1000),
                'interests' => app_list($profile['interests'] ?? []),
                'updated_at' => $now,
            ], '$setOnInsert' => [
                'created_at' => $now,
            ]],
            ['upsert' => true]
        );

        app_mongo_manager()->executeBulkWrite(app_mongo_namespace(), $bulk);

        return mongodb_fetch_profile($userId) ?? [
            'user_id' => $userId,
            'name' => app_text($profile['name'] ?? '', 100),
            'age' => $profile['age'] ?? null,
            'bio' => app_text($profile['bio'] ?? '', 1000),
            'interests' => app_list($profile['interests'] ?? []),
        ];
    }

    function app_redis(): Redis
    {
        static $client = null;

        if ($client instanceof Redis) {
            return $client;
        }

        if (!class_exists('Redis')) {
            json_response(false, 'Redis extension is required.', [], 500);
        }

        $host = app_string('REDIS_HOST', '127.0.0.1');
        $port = app_int('REDIS_PORT', 6379);
        $password = app_string('REDIS_PASSWORD', '');
        $database = app_int('REDIS_DB', 0);
        $timeout = (float) app_string('REDIS_TIMEOUT', '2.5');
        $scheme = strtolower(app_string('REDIS_SCHEME', ''));

        if (app_bool('REDIS_TLS', false) || $scheme === 'tls') {
            $host = str_starts_with($host, 'tls://') ? $host : 'tls://' . $host;
        }

        $client = new Redis();
        $client->connect($host, $port, $timeout);

        if ($password !== '') {
            $client->auth($password);
        }

        if ($database > 0) {
            $client->select($database);
        }

        return $client;
    }

    function redis_session_key(string $token): string
    {
        return app_string('REDIS_PREFIX', 'auth_session:') . $token;
    }

    function redis_store_session(string $token, array $session): bool
    {
        return app_redis()->setex(redis_session_key($token), app_cookie_ttl(), json_encode($session, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    function redis_read_session(string $token): ?array
    {
        $payload = app_redis()->get(redis_session_key($token));

        if (!is_string($payload) || $payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    function redis_delete_session(string $token): void
    {
        app_redis()->del(redis_session_key($token));
    }

    function app_cookie_token(): string
    {
        $name = app_cookie_name();
        return is_string($_COOKIE[$name] ?? null) ? (string) $_COOKIE[$name] : '';
    }

    function app_auth_session(): array
    {
        $token = app_cookie_token();

        if ($token === '') {
            app_error('Authentication required.', 401, [], ['redirect' => 'login.html']);
        }

        $session = redis_read_session($token);

        if (!$session) {
            app_clear_cookie();
            app_error('Authentication required.', 401, [], ['redirect' => 'login.html']);
        }

        $session['last_seen'] = time();
        redis_store_session($token, $session);

        return $session;
    }

    function app_direct_request(string $file): bool
    {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? '';

        return $script !== '' && realpath($file) === realpath($script);
    }
}

if (!app_direct_request(__FILE__)) {
    return;
}

app_bootstrap();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $token = app_cookie_token();
    $session = $token !== '' ? redis_read_session($token) : null;

    if (!$session) {
        json_response(true, 'Authentication status checked.', [
            'authenticated' => false,
        ]);
    }

    json_response(true, 'Authentication status checked.', [
        'authenticated' => true,
        'user' => $session['user'] ?? new stdClass(),
    ]);
}

if ($method !== 'POST') {
    app_error('Method not allowed.', 405);
}

$input = app_request_data();
$identifier = app_text($input['identifier'] ?? '', 120);
$password = (string) ($input['password'] ?? '');
$errors = [];

if ($identifier === '') {
    $errors['identifier'] = 'Username or email is required.';
}

if ($password === '') {
    $errors['password'] = 'Password is required.';
}

if ($errors) {
    app_error('Validation failed.', 422, $errors);
}

$user = mysql_find_user_by_identifier($identifier);

if (!$user || !password_verify($password, (string) $user['password_hash'])) {
    app_error('Invalid credentials.', 401);
}

$profile = mongodb_fetch_profile((int) $user['id']) ?? [
    'user_id' => (int) $user['id'],
    'name' => '',
    'age' => null,
    'bio' => '',
    'interests' => [],
];

$token = bin2hex(random_bytes(32));
$session = [
    'token' => $token,
    'user' => [
        'id' => (int) $user['id'],
        'username' => (string) $user['username'],
        'email' => (string) $user['email'],
    ],
    'created_at' => time(),
    'last_seen' => time(),
];

if (!redis_store_session($token, $session)) {
    app_error('Unable to create session.', 500);
}

app_set_cookie($token);

json_response(true, 'Login successful.', [
    'redirect' => 'profile.html',
    'authenticated' => true,
    'user' => $session['user'],
    'profile' => $profile,
]);
