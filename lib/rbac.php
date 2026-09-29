<?php
declare(strict_types=1);
function user_is_primary_admin(): bool { return (bool)(current_user()['is_primary'] ?? false); }
function can(string $permission): bool {
    if (user_is_primary_admin()) return true;
    static $permissions;
    if ($permissions === null) { $permissions=[]; $st=db()->prepare('SELECT p.permission_key FROM cms_permissions p INNER JOIN cms_role_permissions rp ON rp.permission_id=p.id INNER JOIN cms_user_roles ur ON ur.role_id=rp.role_id WHERE ur.user_id=?'); $st->execute([(int)current_user()['id']]); $permissions=array_column($st->fetchAll(),'permission_key'); }
    return in_array($permission,$permissions,true);
}
function require_permission(string $permission): void { require_login(); if (!can($permission)) { http_response_code(403); exit('You do not have permission to perform this action.'); } }
function audit(string $action, string $entity = '', ?int $entityId = null, array $details = []): void { try { db()->prepare('INSERT INTO cms_audit_logs (user_id,action,entity,entity_id,details,ip_address) VALUES (?,?,?,?,?,?)')->execute([current_user()['id']??null,$action,$entity,$entityId,json_encode($details,JSON_UNESCAPED_SLASHES),$_SERVER['REMOTE_ADDR']??'']); } catch (Throwable) {} }
