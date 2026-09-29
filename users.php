<?php

declare(strict_types=1);
require __DIR__.'/lib/bootstrap.php';
require __DIR__.'/lib/layout.php';
require_permission('users.read');

$error = null;
$roles = db()->query('SELECT id,role_key,name FROM cms_roles ORDER BY is_system DESC,name')->fetchAll();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        verify_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'delete') {
            require_permission('users.delete');
            $id = (int)($_POST['id'] ?? 0);
            $target = db()->prepare('SELECT is_primary FROM cms_users WHERE id=?');
            $target->execute([$id]);
            if ((int)$target->fetchColumn() === 1) throw new RuntimeException('The primary super administrator is protected and cannot be deleted.');
            db()->prepare('DELETE FROM cms_users WHERE id=?')->execute([$id]);
            audit('users.delete', 'user', $id);
            flash('User deleted.');
            redirect('users.php');
        }
        require_permission((int)($_POST['id'] ?? 0) ? 'users.update' : 'users.create');
        $id = (int)($_POST['id'] ?? 0);
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $name = trim((string)($_POST['display_name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        if (!$username || !$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid username, display name and email.');
        if (!$id && strlen($password) < 12) throw new RuntimeException('New users require a password of at least 12 characters.');
        if ($id) {
            $primaryCheck = db()->prepare('SELECT is_primary FROM cms_users WHERE id=?');
            $primaryCheck->execute([$id]);
            if ((int)$primaryCheck->fetchColumn() === 1) $roleId = 0;
        }
        if ($id) {
            if ($password !== '') db()->prepare('UPDATE cms_users SET username=?,email=?,display_name=?,password_hash=? WHERE id=?')->execute([$username,$email,$name,password_hash($password,PASSWORD_DEFAULT),$id]);
            else db()->prepare('UPDATE cms_users SET username=?,email=?,display_name=? WHERE id=?')->execute([$username,$email,$name,$id]);
        } else {
            db()->prepare('INSERT INTO cms_users(username,email,display_name,password_hash) VALUES(?,?,?,?)')->execute([$username,$email,$name,password_hash($password,PASSWORD_DEFAULT)]);
            $id = (int)db()->lastInsertId();
        }
        if ($roleId) {
            db()->prepare('DELETE FROM cms_user_roles WHERE user_id=?')->execute([$id]);
            db()->prepare('INSERT INTO cms_user_roles(user_id,role_id) VALUES(?,?)')->execute([$id,$roleId]);
        }
        audit($id ? 'users.update' : 'users.create', 'user', $id, ['role_id'=>$roleId]);
        flash('User saved.');
        redirect('users.php');
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

$rows = db()->query('SELECT u.id,u.username,u.email,u.display_name,u.status,u.is_primary,u.last_login,u.created_at,r.id AS role_id,r.name AS role_name FROM cms_users u LEFT JOIN cms_user_roles ur ON ur.user_id=u.id LEFT JOIN cms_roles r ON r.id=ur.role_id ORDER BY u.is_primary DESC,u.display_name')->fetchAll();
page_start('Users', 'users');
?>
<div class="page-head"><div><span class="eyebrow">SECURITY</span><h2>User management</h2><p class="muted">Create administrators and assign their exact role permissions.</p></div><?php if (can('users.create')): ?><button class="button primary" type="button" data-open-panel="userCreatePanel">+ New administrator</button><?php endif; ?></div>
<?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
<section class="panel modal-panel" id="userCreatePanel" hidden><div class="panel-head"><h3 id="userPanelTitle">Create subordinate administrator</h3><button class="button ghost" type="button" data-close-panel="userCreatePanel">Cancel</button></div><form method="post" class="form-grid" data-ajax-form data-ajax-action="user.save" data-reload="true"><?=csrf_field()?><input type="hidden" name="id" value="0"><label>Username<input name="username" placeholder="e.g. content.manager" required></label><label>Email<input type="email" name="email" placeholder="admin@example.com" required></label><label>Display name<input name="display_name" placeholder="e.g. Content Manager" required></label><label>Password<input type="password" name="password" placeholder="Use at least 12 characters" minlength="12" required></label><label>Role<select name="role_id" required><?php foreach ($roles as $role): ?><option value="<?=e($role['id'])?>"><?=e($role['name'])?><?= $role['role_key']==='super_admin'?' (full privileges)':''?></option><?php endforeach; ?></select></label><div><button class="button primary" id="userSaveButton">Create administrator</button></div></form></section>
<section class="panel"><div class="bulk-toolbar" data-bulk-toolbar data-bulk-action="users.bulk_delete" data-bulk-ids-name="ids[]" hidden><span><strong data-selected-count>0</strong> selected</span><button class="button danger small" type="button" data-bulk-delete>⌫ Delete selected</button></div><div class="table-wrap"><table><thead><tr><th class="select-col"><?php if (can('users.delete')): ?><input type="checkbox" data-select-all aria-label="Select all users"><?php endif; ?></th><th>#</th><th>User</th><th>Role</th><th>Last login</th><th>Status</th><th class="actions">Actions</th></tr></thead><tbody><?php foreach ($rows as $index => $row): ?><tr data-content-row><td class="select-col"><?php if (can('users.delete') && !$row['is_primary']): ?><input type="checkbox" data-row-select value="<?=e($row['id'])?>" aria-label="Select <?=e($row['display_name'])?>"><?php endif; ?></td><td class="serial"><?=e($index+1)?></td><td><strong><?=e($row['display_name'])?></strong><small class="table-sub"><?=e($row['username'])?> · <?=e($row['email'])?></small></td><td><?=e($row['is_primary']?'Primary super administrator':($row['role_name']??'No role'))?></td><td><?=e($row['last_login']??'Never')?></td><td><span class="badge <?=e($row['status'])?>"><?=e($row['status'])?></span></td><td class="actions action-buttons"><?php if ($row['is_primary']): ?><span class="protected">Protected</span><?php else: ?><?php if (can('users.update')): ?><button class="table-action edit" type="button" data-user-edit data-id="<?=e($row['id'])?>" data-username="<?=e($row['username'])?>" data-email="<?=e($row['email'])?>" data-name="<?=e($row['display_name'])?>" data-role="<?=e($row['role_id']??0)?>"><span aria-hidden="true">✎</span><span>Edit</span></button><?php endif; ?><?php if (can('users.delete')): ?><form method="post" class="inline" data-entity="user" onsubmit="return confirm('Delete this administrator?')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=e($row['id'])?>"><button class="table-action delete" title="Delete administrator"><span aria-hidden="true">⌫</span><span>Delete</span></button></form><?php endif; ?><?php endif; ?></td></tr><?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7" class="empty">No administrators have been created.</td></tr><?php endif; ?></tbody></table></div></section>
<?php page_end();
