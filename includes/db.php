<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(DATA_DIR) && !mkdir(DATA_DIR, 0755, true) && !is_dir(DATA_DIR)) {
        throw new RuntimeException('Cannot create data/ directory. Set permissions to 755.');
    }

    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PHP PDO SQLite is not enabled. In cPanel open Select PHP Version and enable pdo_sqlite + sqlite.');
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = DELETE');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            created_at TEXT NOT NULL,
            last_login_at TEXT
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS otps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL COLLATE NOCASE,
            code_hash TEXT NOT NULL,
            ip TEXT,
            expires_at INTEGER NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL,
            purpose TEXT NOT NULL DEFAULT \'login\'
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS resumes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE,
            slug TEXT NOT NULL UNIQUE COLLATE NOCASE,
            theme TEXT NOT NULL DEFAULT \'classic\',
            raw_text TEXT NOT NULL DEFAULT \'\',
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS rate_limits (
            key TEXT PRIMARY KEY,
            count INTEGER NOT NULL,
            window_start INTEGER NOT NULL
        )'
    );
    $cols = [];
    foreach ($pdo->query('PRAGMA table_info(resumes)')->fetchAll() as $col) {
        $cols[] = (string) $col['name'];
    }
    if (!in_array('source_text', $cols, true)) {
        $pdo->exec("ALTER TABLE resumes ADD COLUMN source_text TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('ai_used', $cols, true)) {
        $pdo->exec('ALTER TABLE resumes ADD COLUMN ai_used INTEGER NOT NULL DEFAULT 0');
    }
    $userCols = [];
    foreach ($pdo->query('PRAGMA table_info(users)')->fetchAll() as $col) {
        $userCols[] = (string) $col['name'];
    }
    if (!in_array('status', $userCols, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN status TEXT NOT NULL DEFAULT 'active'");
    }
    $otpCols = [];
    foreach ($pdo->query('PRAGMA table_info(otps)')->fetchAll() as $col) {
        $otpCols[] = (string) $col['name'];
    }
    if (!in_array('purpose', $otpCols, true)) {
        $pdo->exec("ALTER TABLE otps ADD COLUMN purpose TEXT NOT NULL DEFAULT 'login'");
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL,
            last_login_at TEXT
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT \'\'
        )'
    );
    seed_admin($pdo);
}

function seed_admin(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $email = defined('ADMIN_BOOTSTRAP_EMAIL') ? strtolower(trim(ADMIN_BOOTSTRAP_EMAIL)) : '';
    $pass = defined('ADMIN_BOOTSTRAP_PASSWORD') ? (string) ADMIN_BOOTSTRAP_PASSWORD : '';
    if ($email === '' || $pass === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $pdo->prepare('INSERT INTO admins (email, password_hash, created_at) VALUES (?, ?, ?)')
        ->execute([$email, password_hash($pass, PASSWORD_DEFAULT), iso_now()]);
}

function setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string) $row['value'] : $default;
}

function setting_set(string $key, string $value): void
{
    db()->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')
        ->execute([$key, $value]);
}

function setting_int(string $key, int $default, int $min = 0, int $max = 1000000): int
{
    $raw = setting($key, '');
    $value = $raw === '' ? $default : (int) $raw;
    if ($value < $min) {
        return $min;
    }
    if ($value > $max) {
        return $max;
    }
    return $value;
}

function rate_limit_clear(string $key): void
{
    db()->prepare('DELETE FROM rate_limits WHERE key = ?')->execute([$key]);
}

function rate_limited(string $key, int $max, int $windowSeconds): bool
{
    $pdo = db();
    $now = now();
    $pdo->prepare('DELETE FROM rate_limits WHERE window_start < ?')->execute([$now - 86400]);

    $stmt = $pdo->prepare('SELECT count, window_start FROM rate_limits WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    if (!$row || ($now - (int) $row['window_start']) >= $windowSeconds) {
        $pdo->prepare('INSERT OR REPLACE INTO rate_limits (key, count, window_start) VALUES (?, 1, ?)')
            ->execute([$key, $now]);
        return false;
    }

    $count = (int) $row['count'];
    if ($count >= $max) {
        return true;
    }

    $pdo->prepare('UPDATE rate_limits SET count = count + 1 WHERE key = ?')->execute([$key]);
    return false;
}
