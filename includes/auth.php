<?php

declare(strict_types=1);

function current_user(): ?array
{
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, email, created_at, last_login_at, status FROM users WHERE id = ?');
    $stmt->execute([(int) $id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function user_status(array $user): string
{
    $status = (string) ($user['status'] ?? 'active');
    return in_array($status, ['active', 'pending', 'disabled'], true) ? $status : 'active';
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash_set('error', 'Sign in with your email to continue.');
        redirect('/login.php');
    }
    if (!user_logins_enabled()) {
        logout_user();
        flash_set('error', 'Guest doors are closed for now.');
        redirect('/login.php');
    }
    $status = user_status($user);
    if ($status === 'disabled') {
        logout_user();
        flash_set('error', 'This seat has been withdrawn.');
        redirect('/login.php');
    }
    if ($status === 'pending') {
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script !== 'waiting.php' && $script !== 'logout.php' && $script !== 'account.php') {
            redirect('/waiting.php');
        }
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
    $stmt = $pdo->prepare('SELECT id, email, created_at, last_login_at, status FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $pdo->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')->execute([iso_now(), $user['id']]);
        $user['last_login_at'] = iso_now();
        return $user;
    }
    $status = signup_mode() === 'approval' ? 'pending' : 'active';
    $pdo->prepare('INSERT INTO users (email, created_at, last_login_at, status) VALUES (?, ?, ?, ?)')
        ->execute([$email, iso_now(), iso_now(), $status]);
    $stmt = $pdo->prepare('SELECT id, email, created_at, last_login_at, status FROM users WHERE id = ?');
    $stmt->execute([(int) $pdo->lastInsertId()]);
    return $stmt->fetch();
}

function otp_email_max(): int
{
    return setting_int('otp_email_max', 5, 1, 100);
}

function otp_email_window(): int
{
    return setting_int('otp_window_minutes', 60, 5, 1440) * 60;
}

function otp_ip_max(): int
{
    return setting_int('otp_ip_max', 10, 1, 200);
}

function otp_resend_wait(): int
{
    $fallback = defined('OTP_RESEND_SECONDS') ? OTP_RESEND_SECONDS : 60;
    return setting_int('otp_resend_seconds', $fallback, 0, 600);
}

function otp_try_max(): int
{
    return setting_int('otp_try_max', 20, 3, 200);
}

function otp_unlock_email(string $email): bool
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    rate_limit_clear('otp-email:' . $email);
    rate_limit_clear('otp-action:' . $email);
    db()->prepare('DELETE FROM otps WHERE email = ?')->execute([$email]);
    return true;
}

function otp_email_counters(): array
{
    $window = otp_email_window();
    $max = otp_email_max();
    $now = now();
    $rows = db()->query("SELECT key, count, window_start FROM rate_limits WHERE key LIKE 'otp-email:%' ORDER BY count DESC, window_start DESC")->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $start = (int) $row['window_start'];
        $elapsed = $now - $start;
        if ($elapsed >= $window) {
            continue;
        }
        $count = (int) $row['count'];
        $out[] = [
            'email' => substr((string) $row['key'], strlen('otp-email:')),
            'count' => $count,
            'max' => $max,
            'left' => max(0, $window - $elapsed),
            'blocked' => $count >= $max,
        ];
    }
    return $out;
}

function send_login_otp(string $email): string
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address.';
    }
    if (!user_logins_enabled()) {
        return 'Guest doors are closed for now.';
    }
    $stmt = db()->prepare('SELECT id, status FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    if ($existing && user_status($existing) === 'disabled') {
        return 'This seat has been withdrawn.';
    }
    if (!$existing) {
        $mode = signup_mode();
        if ($mode === 'closed') {
            return 'New guests are not being received.';
        }
    }
    if (rate_limited('otp-ip:' . client_ip(), otp_ip_max(), otp_email_window())) {
        return 'Too many login attempts from this network. Try again later.';
    }
    if (rate_limited('otp-email:' . $email, otp_email_max(), otp_email_window())) {
        return 'Too many codes sent to this email. Try again later.';
    }

    $pdo = db();
    $wait = otp_resend_wait();
    $stmt = $pdo->prepare('SELECT created_at FROM otps WHERE email = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email]);
    $last = $stmt->fetch();
    if ($wait > 0 && $last && (now() - (int) $last['created_at']) < $wait) {
        $secs = $wait - (now() - (int) $last['created_at']);
        return $secs <= 90
            ? 'Please wait ' . max(1, $secs) . ' seconds before requesting another code.'
            : 'Please wait a minute before requesting another code.';
    }

    $code = (string) random_int(100000, 999999);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $pdo->prepare('DELETE FROM otps WHERE email = ? OR expires_at < ?')->execute([$email, now()]);
    $pdo->prepare('INSERT INTO otps (email, code_hash, ip, expires_at, attempts, created_at, purpose) VALUES (?, ?, ?, ?, 0, ?, ?)')
        ->execute([$email, $hash, client_ip(), now() + OTP_TTL_SECONDS, now(), 'login']);

    if (!send_otp_email($email, $code, 'login')) {
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
    if (rate_limited('otp-try:' . client_ip(), otp_try_max(), otp_email_window())) {
        return 'Too many attempts. Try again later.';
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM otps WHERE email = ? AND purpose = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email, 'login']);
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

    $pdo->prepare('DELETE FROM otps WHERE email = ? AND purpose = ?')->execute([$email, 'login']);
    $user = find_or_create_user($email);
    if (user_status($user) === 'disabled') {
        return 'This seat has been withdrawn.';
    }
    login_user($user);
    if (user_status($user) === 'pending') {
        flash_set('ok', 'Your place is reserved. An administrator will admit you.');
    } else {
        flash_set('ok', 'Welcome. Your table is ready.');
    }
    return '';
}

function otp_purposes(): array
{
    return ['login', 'delete_resume', 'delete_account'];
}

function send_action_otp(string $email, string $purpose): string
{
    $email = strtolower(trim($email));
    if (!in_array($purpose, ['delete_resume', 'delete_account'], true)) {
        return 'Unknown confirmation type.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Your account email is not valid.';
    }
    if (rate_limited('otp-ip:' . client_ip(), otp_ip_max(), otp_email_window()) || rate_limited('otp-action:' . $email, otp_email_max(), otp_email_window())) {
        return 'Too many codes sent. Try again later.';
    }
    $pdo = db();
    $wait = otp_resend_wait();
    $stmt = $pdo->prepare('SELECT created_at FROM otps WHERE email = ? AND purpose = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email, $purpose]);
    $last = $stmt->fetch();
    if ($wait > 0 && $last && (now() - (int) $last['created_at']) < $wait) {
        return 'Please wait before requesting another code.';
    }
    $code = (string) random_int(100000, 999999);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $pdo->prepare('DELETE FROM otps WHERE email = ? AND purpose = ?')->execute([$email, $purpose]);
    $pdo->prepare('INSERT INTO otps (email, code_hash, ip, expires_at, attempts, created_at, purpose) VALUES (?, ?, ?, ?, 0, ?, ?)')
        ->execute([$email, $hash, client_ip(), now() + OTP_TTL_SECONDS, now(), $purpose]);
    if (!send_otp_email($email, $code, $purpose)) {
        return 'Could not send the confirmation email.';
    }
    return '';
}

function verify_action_otp(string $email, string $code, string $purpose): string
{
    $email = strtolower(trim($email));
    $code = trim($code);
    if (!in_array($purpose, ['delete_resume', 'delete_account'], true)) {
        return 'Unknown confirmation type.';
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        return 'Enter the 6-digit code from your email.';
    }
    if (rate_limited('otp-try:' . client_ip(), otp_try_max(), otp_email_window())) {
        return 'Too many attempts. Try again later.';
    }
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM otps WHERE email = ? AND purpose = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$email, $purpose]);
    $otp = $stmt->fetch();
    if (!$otp) {
        return 'No confirmation code found. Request a new one.';
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
    $pdo->prepare('DELETE FROM otps WHERE email = ? AND purpose = ?')->execute([$email, $purpose]);
    return '';
}

function delete_user_resume(int $userId): void
{
    db()->prepare('DELETE FROM resumes WHERE user_id = ?')->execute([$userId]);
}

function delete_user_account(int $userId, string $email): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM resumes WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM otps WHERE email = ?')->execute([$email]);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
