<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/config/db.php';

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        $needleLength = strlen($needle);
        if ($needleLength > strlen($haystack)) {
            return false;
        }

        return substr($haystack, -$needleLength) === $needle;
    }
}

$cookieSecure = strtolower((string) ($_ENV['SESSION_COOKIE_SECURE'] ?? 'auto'));
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443');

if ($cookieSecure === 'auto') {
    $cookieSecure = $isHttps ? 'true' : 'false';
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $cookieSecure === 'true',
    'httponly' => true,
    'samesite' => (string) ($_ENV['SESSION_SAMESITE'] ?? 'Lax'),
]);

session_start();

spl_autoload_register(static function (string $class): void {
    $path = dirname(__DIR__) . '/src/' . $class . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

function app_base_path(): string
{
    $basePath = trim((string) ($_ENV['APP_BASE_PATH'] ?? ''), '/');

    return $basePath === '' ? '' : '/' . $basePath;
}

function app_url(string $path = ''): string
{
    $path = ltrim($path, '/');

    if ($path === '') {
        return app_base_path() . '/';
    }

    return app_base_path() . '/' . $path;
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function current_uri_path(): string
{
    $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $basePath = app_base_path();

    if ($basePath !== '' && str_starts_with($requestUri, $basePath)) {
        $requestUri = substr($requestUri, strlen($basePath)) ?: '/';
    }

    return $requestUri;
}

function is_active_path(string $path): bool
{
    $current = rtrim(current_uri_path(), '/') ?: '/';
    $target = '/' . trim($path, '/');
    $target = rtrim($target, '/') ?: '/';

    return $current === $target;
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf']) || $_SESSION['_csrf'] === '') {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf_request(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $provided = (string) ($_POST['_csrf'] ?? '');
    $stored = (string) ($_SESSION['_csrf'] ?? '');

    if ($stored === '' || $provided === '' || !hash_equals($stored, $provided)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function verify_api_csrf_request(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.', 'error' => 'Method not allowed.']);
        exit;
    }

    $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $stored = (string) ($_SESSION['_csrf'] ?? '');

    if ($stored === '' || $provided === '' || !hash_equals($stored, $provided)) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.', 'error' => 'Invalid CSRF token.']);
        exit;
    }
}

function json_internal_error(Throwable $throwable, string $publicMessage = 'Internal server error.'): never
{
    sqlab_log_throwable($throwable, 'app');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $publicMessage, 'error' => $publicMessage]);
    exit;
}

function sqlab_is_production(): bool
{
    return strtolower((string) ($_ENV['APP_ENV'] ?? 'production')) === 'production';
}

function sqlab_storage_path(string $path = ''): string
{
    $base = dirname(__DIR__) . '/storage';

    return $path === '' ? $base : $base . '/' . ltrim($path, '/\\');
}

function sqlab_log_throwable(Throwable $throwable, string $channel = 'app'): void
{
    $logDir = sqlab_storage_path('logs');

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $safeChannel = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $channel) ?: 'app';
    $logFile = $logDir . '/' . $safeChannel . '.log';
    $entry = sprintf(
        "[%s] %s: %s in %s:%d\n%s\n\n",
        date('c'),
        get_class($throwable),
        $throwable->getMessage(),
        $throwable->getFile(),
        $throwable->getLine(),
        $throwable->getTraceAsString()
    );

    if (@file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX) === false) {
        error_log('[sqlab] ' . $entry);
    }
}

function sqlab_client_ip(): string
{
    $candidates = [
        (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim(explode(',', $candidate)[0] ?? '');

        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return '0.0.0.0';
}

function sqlab_rate_limit_hit(string $scope, string $actor, int $maxHits, int $windowSeconds): array
{
    $scope = trim($scope);
    $actor = trim($actor);

    if ($scope === '' || $actor === '' || $maxHits <= 0 || $windowSeconds <= 0) {
        return ['allowed' => true, 'retry_after' => 0, 'hits' => 0];
    }

    try {
        $pdo = DB::getConnection();
        sqlab_ensure_rate_limit_table($pdo);

        $now = time();
        $windowStart = intdiv($now, $windowSeconds) * $windowSeconds;

        $insert = $pdo->prepare(
            'INSERT INTO request_rate_limits (scope, actor, window_start, hits, updated_at)
             VALUES (:scope, :actor, :window_start, 1, NOW())
             ON DUPLICATE KEY UPDATE hits = hits + 1, updated_at = NOW()'
        );
        $insert->execute([
            'scope' => $scope,
            'actor' => $actor,
            'window_start' => $windowStart,
        ]);

        $hitsStmt = $pdo->prepare(
            'SELECT hits FROM request_rate_limits
             WHERE scope = :scope AND actor = :actor AND window_start = :window_start
             LIMIT 1'
        );
        $hitsStmt->execute([
            'scope' => $scope,
            'actor' => $actor,
            'window_start' => $windowStart,
        ]);

        $hits = (int) ($hitsStmt->fetchColumn() ?: 0);
        $allowed = $hits <= $maxHits;
        $retryAfter = $allowed ? 0 : max(1, ($windowStart + $windowSeconds) - $now);

        if (random_int(1, 100) <= 2) {
            $cleanupSeconds = max($windowSeconds * 6, 3600);
            $pdo->exec('DELETE FROM request_rate_limits WHERE updated_at < DATE_SUB(NOW(), INTERVAL ' . (int) $cleanupSeconds . ' SECOND)');
        }

        return ['allowed' => $allowed, 'retry_after' => $retryAfter, 'hits' => $hits];
    } catch (Throwable $throwable) {
        sqlab_log_throwable($throwable, 'rate_limit');

        return ['allowed' => true, 'retry_after' => 0, 'hits' => 0];
    }
}

function sqlab_ensure_rate_limit_table(PDO $pdo): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS request_rate_limits (
            scope VARCHAR(64) NOT NULL,
            actor VARCHAR(190) NOT NULL,
            window_start INT NOT NULL,
            hits INT NOT NULL DEFAULT 0,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (scope, actor, window_start),
            KEY idx_request_rate_limits_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $initialized = true;
}

function safe_inline_svg(string $svg): string
{
    $svg = trim($svg);

    if ($svg === '') {
        return '';
    }

    // Keep only common SVG tags, then strip risky attributes/protocols.
    $allowed = '<svg><path><circle><rect><line><polyline><polygon><g><ellipse>';
    $sanitized = strip_tags($svg, $allowed);
    $sanitized = preg_replace('/\son[a-zA-Z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/\s(?:href|xlink:href)\s*=\s*("|\')\s*javascript:[^"\']*(\1)/i', '', $sanitized) ?? $sanitized;

    if (!str_contains(strtolower($sanitized), '<svg')) {
        return '';
    }

    return $sanitized;
}

function sqlab_rewrite_sql_with_map(string $sql, array $tableMap): string
{
    if ($sql === '' || $tableMap === []) {
        return $sql;
    }

    uksort($tableMap, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

    $result = '';
    $buffer = '';
    $length = strlen($sql);
    $state = 'normal';

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($state === 'normal') {
            $startsLineComment = ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2])));
            $startsHashComment = ($char === '#');
            $startsBlockComment = ($char === '/' && $next === '*');
            $startsSingle = ($char === "'");
            $startsDouble = ($char === '"');
            $startsBacktick = ($char === '`');

            if ($startsLineComment || $startsHashComment || $startsBlockComment || $startsSingle || $startsDouble || $startsBacktick) {
                if ($buffer !== '') {
                    $result .= sqlab_rewrite_sql_plain_segment($buffer, $tableMap);
                    $buffer = '';
                }

                if ($startsLineComment) {
                    $state = 'line_comment';
                    $result .= $char;
                    $result .= $next;
                    $i++;
                    continue;
                }

                if ($startsHashComment) {
                    $state = 'line_comment';
                    $result .= $char;
                    continue;
                }

                if ($startsBlockComment) {
                    $state = 'block_comment';
                    $result .= $char;
                    $result .= $next;
                    $i++;
                    continue;
                }

                if ($startsSingle) {
                    $state = 'single_quote';
                    $result .= $char;
                    continue;
                }

                if ($startsDouble) {
                    $state = 'double_quote';
                    $result .= $char;
                    continue;
                }

                if ($startsBacktick) {
                    $state = 'backtick';
                    $result .= $char;
                    continue;
                }
            }

            $buffer .= $char;
            continue;
        }

        if ($state === 'line_comment') {
            $result .= $char;

            if ($char === "\n") {
                $state = 'normal';
            }

            continue;
        }

        if ($state === 'block_comment') {
            $result .= $char;

            if ($char === '*' && $next === '/') {
                $result .= $next;
                $i++;
                $state = 'normal';
            }

            continue;
        }

        if ($state === 'single_quote') {
            $result .= $char;

            if ($char === "'" && $next === "'") {
                $result .= $next;
                $i++;
                continue;
            }

            if ($char === "'" && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $state = 'normal';
            }

            continue;
        }

        if ($state === 'double_quote') {
            $result .= $char;

            if ($char === '"' && $next === '"') {
                $result .= $next;
                $i++;
                continue;
            }

            if ($char === '"' && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $state = 'normal';
            }

            continue;
        }

        if ($state === 'backtick') {
            $result .= $char;

            if ($char === '`') {
                $state = 'normal';
            }

            continue;
        }
    }

    if ($buffer !== '') {
        $result .= sqlab_rewrite_sql_plain_segment($buffer, $tableMap);
    }

    return $result;
}

function sqlab_rewrite_sql_plain_segment(string $segment, array $tableMap): string
{
    if ($segment === '' || $tableMap === []) {
        return $segment;
    }

    foreach ($tableMap as $logical => $physical) {
        $quotedPattern = '/`' . preg_quote($logical, '/') . '`/i';
        $plainPattern = '/\b' . preg_quote($logical, '/') . '\b/i';
        $segment = preg_replace($quotedPattern, '`' . $physical . '`', $segment) ?? $segment;
        $segment = preg_replace($plainPattern, $physical, $segment) ?? $segment;
    }

    return $segment;
}

function sqlab_translate_oracle_sql(string $sql): string
{
    $enabled = strtolower((string) ($_ENV['SQL_ACCEPT_ORACLE_SYNTAX'] ?? 'true')) !== 'false';

    if (!$enabled || trim($sql) === '') {
        return $sql;
    }

    $translated = $sql;

    // Common Oracle scalar functions.
    $translated = preg_replace('/\bNVL\s*\(/i', 'IFNULL(', $translated) ?? $translated;
    $translated = preg_replace('/\bSYSTIMESTAMP\b/i', 'CURRENT_TIMESTAMP', $translated) ?? $translated;
    $translated = preg_replace('/\bSYSDATE\b/i', 'CURRENT_DATE', $translated) ?? $translated;

    // Common Oracle datatypes.
    $translated = preg_replace('/\bVARCHAR2\s*\(/i', 'VARCHAR(', $translated) ?? $translated;
    $translated = preg_replace('/\bNVARCHAR2\s*\(/i', 'VARCHAR(', $translated) ?? $translated;
    $translated = preg_replace('/\bNUMBER\s*\(\s*(\d+)\s*,\s*(\d+)\s*\)/i', 'DECIMAL($1,$2)', $translated) ?? $translated;
    $translated = preg_replace('/\bNUMBER\s*\(\s*(\d+)\s*\)/i', 'DECIMAL($1,0)', $translated) ?? $translated;
    $translated = preg_replace('/\bNUMBER\b/i', 'DECIMAL(18,0)', $translated) ?? $translated;
    $translated = preg_replace('/\bCLOB\b/i', 'LONGTEXT', $translated) ?? $translated;

    // Common Oracle literals.
    $translated = preg_replace('/\bTO_DATE\s*\(\s*\'([^\']+)\'\s*,\s*\'YYYY-MM-DD\'\s*\)/i', "STR_TO_DATE('$1','%Y-%m-%d')", $translated) ?? $translated;

    // Oracle pagination style: FETCH FIRST/NEXT n ROWS ONLY -> LIMIT n
    $translated = preg_replace('/\s+FETCH\s+(?:FIRST|NEXT)\s+(\d+)\s+ROWS\s+ONLY\s*$/i', ' LIMIT $1', $translated) ?? $translated;

    // Keep FROM DUAL compatible with MySQL by leaving as-is.

    return $translated;
}

function set_auth_flash(string $type, string $message): void
{
    $_SESSION['auth_flash'] = ['type' => $type, 'message' => $message];
}

function pull_auth_flash(): ?array
{
    $flash = $_SESSION['auth_flash'] ?? null;
    unset($_SESSION['auth_flash']);

    return is_array($flash) ? $flash : null;
}

function set_active_subject_slug(string $slug): void
{
    $slug = strtolower(trim($slug));

    if ($slug !== '') {
        $_SESSION['active_subject_slug'] = $slug;
    }
}

function get_active_subject_slug(): string
{
    $slug = strtolower(trim((string) ($_SESSION['active_subject_slug'] ?? 'sql')));

    return $slug === '' ? 'sql' : $slug;
}

function get_active_subject(): array
{
    $subject = Subject::findBySlug(get_active_subject_slug());

    if ($subject) {
        return $subject;
    }

    $all = Subject::allActive();

    if ($all) {
        set_active_subject_slug((string) $all[0]['slug']);
        return $all[0];
    }

    return [
        'id' => 1,
        'slug' => 'sql',
        'name' => 'SQL',
        'description' => 'Querying, joins, and database problem solving.',
        'is_active' => 1,
        'sort_order' => 1,
    ];
}

