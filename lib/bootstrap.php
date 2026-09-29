<?php
declare(strict_types=1);

const MASTER_CMS_ROOT = __DIR__ . '/..';
if (is_file(MASTER_CMS_ROOT . '/vendor/autoload.php')) require_once MASTER_CMS_ROOT . '/vendor/autoload.php';
$GLOBALS['master_config'] = require MASTER_CMS_ROOT . '/config.php';

function cfg(string $path, mixed $default = null): mixed {
    $value = $GLOBALS['master_config'];
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function ui_icon_svg(string $name): string {
    $paths = [
        'trash' => '<path d="M3 6h18M9 6V4h6v2m-8 0 1 14h8l1-14M10 10v6m4-6v6"/>',
        'gallery' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.4"/><path d="m4 18 5.5-5 3.2 3 2.5-2.4L20 18"/>',
    ];
    $path = $paths[$name] ?? $paths['gallery'];
    return '<svg class="ui-icon ui-icon-'.$name.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$path.'</svg>';
}
function media_url(?string $path): string {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('#^(?:https?:)?//#i', $path)) return $path;
    return rtrim((string)cfg('admin.uploads_url', 'uploads'), '/') . '/' . ltrim($path, '/');
}
function redirect(string $path): never { header('Location: ' . $path); exit; }
function db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $c = cfg('db');
    $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}", $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
function slugify(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-') ?: 'item-' . bin2hex(random_bytes(3));
}
function clean_html(string $html): string {
    $html=strip_tags($html,'<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a><img><figure><figcaption><hr>');
    $html=preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$html)??'';
    return preg_replace('/(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>]*/i','$1=$2#',$html)??$html;
}
function flash(?string $message = null, string $type = 'success'): ?array {
    if ($message !== null) { $_SESSION['flash'] = ['message'=>$message,'type'=>$type]; return null; }
    $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $flash;
}
function csrf_token(): string { return $_SESSION['csrf'] ?? ''; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void {
    if (!isset($_POST['_csrf']) || !hash_equals(csrf_token(), (string)$_POST['_csrf'])) { header('HTTP/1.1 419 Session Expired'); exit('Invalid or expired request.'); }
}
function store_upload(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('The upload failed.');
    if ((int)$file['size'] > (int)cfg('admin.max_upload')) throw new RuntimeException('The upload is too large.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']);
    $allowed = cfg('admin.allowed_mime', []);
    if (!isset($allowed[$mime])) throw new RuntimeException('This file type is not allowed.');
    $stored = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $directory = (string)cfg('admin.uploads_dir');
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('Unable to create the upload directory.');
    if (!move_uploaded_file((string)$file['tmp_name'], $directory . '/' . $stored)) throw new RuntimeException('Unable to store the upload.');
    db()->prepare('INSERT INTO cms_media(file_name,stored_name,mime_type,file_size,uploaded_by) VALUES(?,?,?,?,?)')->execute([(string)$file['name'],$stored,$mime,(int)$file['size'],current_user()['id']??null]);
    return $stored;
}
function current_user(): ?array { return $_SESSION['master_user'] ?? null; }
function db_setting(string $key, mixed $default = null): mixed {
    static $cache;
    if ($cache === null) { $cache = []; try { foreach (db()->query('SELECT setting_key,setting_value FROM cms_settings')->fetchAll() as $row) $cache[$row['setting_key']] = $row['setting_value']; } catch (Throwable) {} }
    return array_key_exists($key, $cache) && $cache[$key] !== '' ? $cache[$key] : $default;
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/modules.php';
auth_boot();
