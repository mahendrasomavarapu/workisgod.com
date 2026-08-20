<?php

declare(strict_types=1);

function current_user(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, email, created_at, last_login_at FROM users WHERE id = ?');
    $stmt->execute([(int) $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash_set('error', 'Sign in with your email to continue.');
        redirect('/login.php');
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function current_admin(): ?array
{
    $id = $_SESSION['admin_id'] ?? null;
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, email, created_at, last_login_at FROM admins WHERE id = ?');
    $stmt->execute([(int) $id]);
    $admin = $stmt->fetch();
    return $admin ?: null;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        flash_set('error', 'Admin sign-in required.');
        redirect('/admin/login.php');
    }
    return $admin;
}

function login_admin(array $admin): void
{
    session_regenerate_id(true);
    unset($_SESSION['user_id']);
    $_SESSION['admin_id'] = (int) $admin['id'];
}

function verify_admin_login(string $email, string $password): string
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        return 'Enter your admin email and password.';
    }
    if (rate_limited('admin-ip:' . client_ip(), 12, 3600)) {
        return 'Too many admin login attempts. Try later.';
    }
    $stmt = db()->prepare('SELECT * FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return 'Those admin credentials are not correct.';
    }
    db()->prepare('UPDATE admins SET last_login_at = ? WHERE id = ?')->execute([iso_now(), $admin['id']]);
    login_admin($admin);
    return '';
}

function find_or_create_user(string $email): array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, email, created_at, last_login_at FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $pdo->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([iso_now(), $user['id']]);
        $user['last_login_at'] = iso_now();
        return $user;
    }
    $pdo->prepare('INSERT INTO users (email, created_at, last_login_at) VALUES (?, ?, ?)')
        ->execute([$email, iso_now(), iso_now()]);
    $stmt = $pdo->prepare('SELECT id, email, created_at, last_login_at FROM users WHERE id = ?');
    $stmt->execute([(int) $pdo->lastInsertId()]);
    return $stmt->fetch();
}

function send_login_otp(string $email): string
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address.';
    }
    if (setting('signups_open', '1') !== '1') {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if (!$stmt->fetch()) {
            return 'New sign-ins are paused by the administrator.';
        }
    }
    if (rate_limited('otp-ip:' . client_ip(), 10, 3600)) {
        return 'Too many login attempts from this network. Try again later.';
    }
    if (rate_limited('otp-email:' . $email, 5, 3600)) {
        return 'Too many codes sent to this email. Try again later.';
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT created_at FROM otps WHERE email = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email]);
    $last = $stmt->fetch();
    if ($last && (now() - (int) $last['created_at']) < OTP_RESEND_SECONDS) {
        return 'Please wait a minute before requesting another code.';
    }

    $code = (string) random_int(100000, 999999);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $pdo->prepare('DELETE FROM otps WHERE email = ? OR expires_at < ?')->execute([$email, now()]);
    $pdo->prepare('INSERT INTO otps (email, code_hash, ip, expires_at, attempts, created_at) VALUES (?, ?, ?, ?, 0, ?)')
        ->execute([$email, $hash, client_ip(), now() + OTP_TTL_SECONDS, now()]);

    if (!send_otp_email($email, $code)) {
        return 'Could not send email. Check SMTP settings in config.php.';
    }
    return '';
}

function verify_login_otp(string $email, string $code): string
{
    $email = strtolower(trim($email));
    $code = trim($code);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address.';
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        return 'Enter the 6-digit code from your email.';
    }
    if (rate_limited('otp-try:' . client_ip(), 20, 3600)) {
        return 'Too many attempts. Try again later.';
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM otps WHERE email = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email]);
    $otp = $stmt->fetch();
    if (!$otp) {
        return 'No login code found. Request a new one.';
    }
    if ((int) $otp['expires_at'] < now()) {
        return 'That code has expired. Request a new one.';
    }
    if ((int) $otp['attempts'] >= 5) {
        return 'Too many incorrect tries. Request a new code.';
    }

    $pdo->prepare('UPDATE otps SET attempts = attempts + 1 WHERE id = ?')->execute([$otp['id']]);
    if (!password_verify($code, $otp['code_hash'])) {
        return 'That code is incorrect.';
    }

    $pdo->prepare('DELETE FROM otps WHERE email = ?')->execute([$email]);
    $user = find_or_create_user($email);
    login_user($user);
    return '';
}
