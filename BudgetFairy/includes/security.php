<?php
declare(strict_types=1);


function start_secure_session(): void {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('bf_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();

   
    $now = time();
    if (isset($_SESSION['last_seen']) && $now - $_SESSION['last_seen'] > (int)config('session_lifetime')) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = $now;

    
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; "
         . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
         . "font-src https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'");
    if ($https) header('Strict-Transport-Security: max-age=31536000');
}


function current_user_id(): ?int { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }

function current_user(): ?array {
    $id = current_user_id();
    if (!$id) return null;
    static $user = false;
    if ($user === false) {
        $st = db()->prepare('SELECT id, name, email FROM users WHERE id = ?');
        $st->execute([$id]);
        $user = $st->fetch() ?: null;
        if (!$user) { $_SESSION = []; }
    }
    return $user;
}

function require_login(): int {
    $id = current_user_id();
    if (!$id || !current_user()) redirect('login.php');
    return $id;
}

function login_user(int $id): void {
    session_regenerate_id(true);            
    $_SESSION = ['user_id' => $id, 'last_seen' => time()];
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}


function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_valid(?string $token): bool {
    return is_string($token) && $token !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}
/** For HTML form posts. */
function csrf_check_form(): void {
    if (!csrf_valid($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Session expired. Go back, refresh the page and try again.'); }
}


function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? 'cli'; }   // don't trust X-Forwarded-For unless behind your own proxy


function rate_limit_hit(string $name, string $subject): bool {
    [$max, $window] = config("rate_limits.$name", [60, 60]);
    $bucket = substr($name . ':' . hash('sha256', $subject), 0, 190);
    $start = intdiv(time(), $window) * $window;
    $pdo = db();
    $pdo->prepare('INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?,?,1) ON DUPLICATE KEY UPDATE hits = hits + 1')
        ->execute([$bucket, $start]);
    $st = $pdo->prepare('SELECT hits FROM rate_limits WHERE bucket = ? AND window_start = ?');
    $st->execute([$bucket, $start]);
    if (random_int(1, 50) === 1) $pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([time() - 86400]);
    return (int)$st->fetchColumn() <= $max;
}


function api_guard(string $limitName = 'api_write'): int {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);
    $uid = current_user_id();
    if (!$uid || !current_user()) jsonResponse(['error' => 'Please log in again.'], 401);
    if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) jsonResponse(['error' => 'Security token expired. Refresh the page.'], 419);
    if (!rate_limit_hit($limitName, (string)$uid)) { header('Retry-After: 60'); jsonResponse(['error' => 'Too many requests. Please wait a moment.'], 429); }
    return $uid;
}
