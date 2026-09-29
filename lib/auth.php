<?php
declare(strict_types=1);
function auth_boot(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        session_name((string)cfg('admin.session_name'));
        session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])]);
        session_start();
    }
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    if (!headers_sent()) { header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: SAMEORIGIN'); header('Referrer-Policy: same-origin'); }
}
function require_login(): array { $user = current_user(); if (!$user) redirect('login.php'); return $user; }
function login_attempt(string $identity, string $password): bool {
    if (($_SESSION['login_locked_until'] ?? 0) > time()) return false;
    $st = db()->prepare('SELECT * FROM cms_users WHERE (username = ? OR email = ?) AND status = "active" LIMIT 1'); $st->execute([$identity,$identity]); $user = $st->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) { $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1; if ($_SESSION['login_fails'] >= 8) $_SESSION['login_locked_until'] = time()+300; return false; }
    session_regenerate_id(true); $_SESSION['login_fails'] = 0; unset($user['password_hash']); $_SESSION['master_user'] = $user;
    db()->prepare('UPDATE cms_users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]); audit('auth.login','user',(int)$user['id'],['identity'=>$identity]); return true;
}
function logout_user(): void { if (current_user()) audit('auth.logout','user',(int)current_user()['id']); $_SESSION=[]; if (ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',$p['secure']?time()-42000:time()-42000,$p['path'],$p['domain']??'',$p['secure'],$p['httponly']); } session_destroy(); }
