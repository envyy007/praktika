<?php
/** PDO и подготовленные запросы. Ошибка соединения не завершает скрипт. */

require_once __DIR__ . '/config.php';

function db_server(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}

function bind_params(PDOStatement $st, array $params): void
{
    foreach ($params as $key => $value) {
        $type = is_int($value) ? PDO::PARAM_INT : (is_null($value) ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $st->bindValue($key, $value, $type);
    }
}

function q(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    bind_params($st, $params);
    $st->execute();
    return $st->fetchAll();
}

function q1(string $sql, array $params = []): ?array
{
    $rows = q($sql, $params);
    return $rows ? $rows[0] : null;
}

function ex(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    bind_params($st, $params);
    $st->execute();
    return $st->rowCount();
}

function scalar(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    bind_params($st, $params);
    $st->execute();
    return $st->fetchColumn();
}

function last_id(): int
{
    return (int)db()->lastInsertId();
}

/** Готовность базы: сервер → база → таблицы. */
function readiness(bool $fresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$fresh) return $cache;

    try {
        $srv = db_server();
    } catch (Throwable $e) {
        return $cache = ['ok' => false, 'reason' => 'Сервер MySQL недоступен: ' . $e->getMessage(),
            'hint' => 'Запустите MySQL и проверьте доступы в inc/config.php.'];
    }
    try {
        $st = $srv->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :n');
        $st->execute([':n' => DB_NAME]);
        if (!$st->fetchColumn()) {
            return $cache = ['ok' => false, 'reason' => 'База ' . DB_NAME . ' ещё не создана.',
                'hint' => 'Откройте install.php и нажмите «Установить».'];
        }
        $st = $srv->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :n');
        $st->execute([':n' => DB_NAME]);
        if ((int)$st->fetchColumn() < 20) {
            return $cache = ['ok' => false, 'reason' => 'Таблицы базы созданы не полностью.',
                'hint' => 'Откройте install.php и нажмите «Установить».'];
        }
    } catch (Throwable $e) {
        return $cache = ['ok' => false, 'reason' => $e->getMessage(), 'hint' => 'Проверьте права пользователя MySQL.'];
    }
    return $cache = ['ok' => true, 'reason' => '', 'hint' => ''];
}

function sql_statements(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $out = [];
    foreach (explode(';', $sql) as $chunk) {
        $chunk = trim($chunk);
        if ($chunk !== '') $out[] = $chunk;
    }
    return $out;
}

function log_error(string $message, string $context = ''): void
{
    try {
        ex('INSERT INTO error_log (message, context, created_at) VALUES (:m, :c, NOW())',
           [':m' => mb_substr($message, 0, 500), ':c' => mb_substr($context, 0, 200)]);
    } catch (Throwable $e) { /* журнал недоступен */ }
}