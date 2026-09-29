<?php
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/content.php';

header('Content-Type: application/json; charset=utf-8');
if (!current_user()) { http_response_code(401); echo json_encode(['ok'=>false,'message'=>'Your session has expired.']); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'message'=>'POST required.']); exit; }
try {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'settings.save') {
        require_permission('settings.update');
        foreach ($_POST['module_enabled'] ?? [] as $key => $value) { $definition=module_def((string)$key); if(!$definition||(($definition['system']??false)&&in_array($key,['media','settings','users','roles','audit'],true))) continue; db()->prepare('UPDATE cms_modules SET is_enabled=? WHERE module_key=?')->execute([(int)$value, $key]); }
        if ((string)($_POST['modules_form'] ?? '') === '1') foreach (cfg('modules', []) as $key => $def) if (!array_key_exists($key, $_POST['module_enabled'] ?? []) && !($def['system'] ?? false)) db()->prepare('UPDATE cms_modules SET is_enabled=0 WHERE module_key=?')->execute([$key]);
        foreach ($_POST['setting'] ?? [] as $key => $value) db()->prepare('INSERT INTO cms_settings(setting_key,setting_value,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)')->execute([$key, (string)$value, current_user()['id']]);
        audit('settings.update', 'settings'); echo json_encode(['ok'=>true,'message'=>'Settings saved successfully.']); exit;
    }
    if ($action === 'content.delete') {
        $module = (string)($_POST['module'] ?? ''); $id = (int)($_POST['id'] ?? 0); $def = module_def($module);
        if (!$def || ($def['system'] ?? false)) throw new RuntimeException('Invalid content module.');
        require_permission("$module.delete"); if (!content_move_to_trash($id, $module)) throw new RuntimeException('This item is already in trash or no longer exists.'); audit("$module.trash",$module,$id); echo json_encode(['ok'=>true,'message'=>$def['label'].' item moved to trash.']); exit;
    }
    if ($action === 'content.bulk_delete') {
        $module=(string)($_POST['module']??'');$ids=array_values(array_filter(array_map('intval',$_POST['ids']??[])));$def=module_def($module);
        if(!$def||($def['system']??false)||!$ids)throw new RuntimeException('Select at least one item.');
        require_permission("$module.delete");$in=implode(',',array_fill(0,count($ids),'?'));$params=array_merge([$module],$ids);
        ensure_content_trash_schema(); $st=db()->prepare("UPDATE cms_content SET deleted_at=NOW(), deleted_by=? WHERE module_key=? AND deleted_at IS NULL AND id IN ($in)");$st->execute(array_merge([(int)current_user()['id'], $module],$ids));$count=$st->rowCount();
        audit("$module.trash",$module,null,['bulk'=>true,'count'=>$count,'ids'=>$ids]);echo json_encode(['ok'=>true,'message'=>"$count ".strtolower($def['label']).' moved to trash.']);exit;
    }
    if ($action === 'user.save') {
        $id=(int)($_POST['id']??0); require_permission($id?'users.update':'users.create');
        $username=trim((string)($_POST['username']??''));$email=trim((string)($_POST['email']??''));$name=trim((string)($_POST['display_name']??''));$password=(string)($_POST['password']??'');$roleId=(int)($_POST['role_id']??0);
        if(!$username||!filter_var($email,FILTER_VALIDATE_EMAIL)||!$name)throw new RuntimeException('Username, email and display name are required.');
        if(!$id&&strlen($password)<12)throw new RuntimeException('New users require a password of at least 12 characters.');
        if($id){$primaryCheck=db()->prepare('SELECT is_primary FROM cms_users WHERE id=?');$primaryCheck->execute([$id]);if((int)$primaryCheck->fetchColumn()===1)$roleId=0;}
        if($id){if($password!=='')db()->prepare('UPDATE cms_users SET username=?,email=?,display_name=?,password_hash=? WHERE id=?')->execute([$username,$email,$name,password_hash($password,PASSWORD_DEFAULT),$id]);else db()->prepare('UPDATE cms_users SET username=?,email=?,display_name=? WHERE id=?')->execute([$username,$email,$name,$id]);}
        else{db()->prepare('INSERT INTO cms_users(username,email,display_name,password_hash) VALUES(?,?,?,?)')->execute([$username,$email,$name,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)db()->lastInsertId();}
        if($roleId){db()->prepare('DELETE FROM cms_user_roles WHERE user_id=?')->execute([$id]);db()->prepare('INSERT INTO cms_user_roles(user_id,role_id) VALUES(?,?)')->execute([$id,$roleId]);}
        audit($id?'users.update':'users.create','user',$id,['role_id'=>$roleId]); echo json_encode(['ok'=>true,'message'=>'Administrator saved.']); exit;
    }
    if ($action === 'user.delete') {
        require_permission('users.delete');$id=(int)($_POST['id']??0);$st=db()->prepare('SELECT is_primary FROM cms_users WHERE id=?');$st->execute([$id]);if((int)$st->fetchColumn()===1)throw new RuntimeException('The primary super administrator is protected and cannot be deleted.');db()->prepare('DELETE FROM cms_users WHERE id=?')->execute([$id]);audit('users.delete','user',$id);echo json_encode(['ok'=>true,'message'=>'Administrator deleted.']);exit;
    }
    if ($action === 'users.bulk_delete') {
        $ids=array_values(array_filter(array_map('intval',$_POST['ids']??[])));if(!$ids)throw new RuntimeException('Select at least one administrator.');require_permission('users.delete');
        $in=implode(',',array_fill(0,count($ids),'?'));$st=db()->prepare("SELECT id,is_primary FROM cms_users WHERE id IN ($in)");$st->execute($ids);$safe=[];foreach($st->fetchAll() as $user){if(!(int)$user['is_primary'])$safe[]=(int)$user['id'];}
        if(!$safe)throw new RuntimeException('The primary super administrator is protected and cannot be deleted.');$in=implode(',',array_fill(0,count($safe),'?'));db()->prepare("DELETE FROM cms_users WHERE id IN ($in)")->execute($safe);audit('users.delete','user',null,['bulk'=>true,'count'=>count($safe),'ids'=>$safe]);echo json_encode(['ok'=>true,'message'=>count($safe).' administrators deleted.']);exit;
    }
    if ($action === 'media.upload') {
        require_permission('media.create'); if(empty($_FILES['file']))throw new RuntimeException('Choose at least one file.');
        $files=[];
        if(is_array($_FILES['file']['name']??null))foreach(array_keys($_FILES['file']['name']) as $index)$files[]=['name'=>$_FILES['file']['name'][$index],'type'=>$_FILES['file']['type'][$index],'tmp_name'=>$_FILES['file']['tmp_name'][$index],'error'=>$_FILES['file']['error'][$index],'size'=>$_FILES['file']['size'][$index]];
        else $files[]=$_FILES['file'];
        $uploaded=0;$alt=trim((string)($_POST['alt_text']??''));
        foreach($files as $file){$stored=store_upload($file);$id=(int)db()->lastInsertId();db()->prepare('UPDATE cms_media SET alt_text=? WHERE id=?')->execute([$alt,$id]);audit('media.create','media',$id);$uploaded++;}
        echo json_encode(['ok'=>true,'message'=>$uploaded.' media file'.($uploaded===1?'':'s').' uploaded.','count'=>$uploaded]);exit;
    }
    if ($action === 'media.bulk_delete') {
        $ids=array_values(array_filter(array_map('intval',$_POST['ids']??[])));if(!$ids)throw new RuntimeException('Select at least one media item.');require_permission('media.delete');$in=implode(',',array_fill(0,count($ids),'?'));$st=db()->prepare("SELECT stored_name FROM cms_media WHERE id IN ($in)");$st->execute($ids);$files=$st->fetchAll();$deleted=0;foreach($files as $file){$path=(string)cfg('admin.uploads_dir').'/'.$file['stored_name'];if(is_file($path))unlink($path);$deleted++;}db()->prepare("DELETE FROM cms_media WHERE id IN ($in)")->execute($ids);audit('media.delete','media',null,['bulk'=>true,'count'=>$deleted,'ids'=>$ids]);echo json_encode(['ok'=>true,'message'=>"$deleted media items deleted."]);exit;
    }
    if ($action === 'mail.save') {
        require_permission('mail.update');$key=trim((string)($_POST['template_key']??''));$subject=trim((string)($_POST['subject']??''));$body=trim((string)($_POST['body']??''));if(!$key||!$subject||!$body)throw new RuntimeException('Template key, subject and body are required.');db()->prepare('INSERT INTO cms_mail_templates(template_key,subject,body) VALUES(?,?,?) ON DUPLICATE KEY UPDATE subject=VALUES(subject),body=VALUES(body)')->execute([$key,$subject,$body]);audit('mail.update','mail');echo json_encode(['ok'=>true,'message'=>'Mail template saved.']);exit;
    }
    if ($action === 'account.save') {
        $user=require_login();$name=trim((string)($_POST['display_name']??''));$email=trim((string)($_POST['email']??''));$password=(string)($_POST['password']??'');$confirmation=(string)($_POST['password_confirmation']??'');if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid name and email.');if($password!==''&&($password!==$confirmation||strlen($password)<12))throw new RuntimeException('New passwords must match and contain at least 12 characters.');if($password!=='')db()->prepare('UPDATE cms_users SET display_name=?,email=?,password_hash=? WHERE id=?')->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$user['id']]);else db()->prepare('UPDATE cms_users SET display_name=?,email=? WHERE id=?')->execute([$name,$email,$user['id']]);$_SESSION['master_user']['display_name']=$name;$_SESSION['master_user']['email']=$email;audit('account.update','user',(int)$user['id']);echo json_encode(['ok'=>true,'message'=>'Account updated.']);exit;
    }
    if ($action === 'roles.permissions') {
        require_permission('roles.update');$id=(int)($_POST['role_id']??0);$st=db()->prepare('SELECT role_key,is_system FROM cms_roles WHERE id=?');$st->execute([$id]);$role=$st->fetch();if(!$role)throw new RuntimeException('Role not found.');if((int)$role['is_system']===1)throw new RuntimeException('System roles are protected. Create a custom role for delegated access.');$selected=array_map('intval',$_POST['permissions']??[]);db()->prepare('DELETE FROM cms_role_permissions WHERE role_id=?')->execute([$id]);$ins=db()->prepare('INSERT INTO cms_role_permissions(role_id,permission_id) VALUES(?,?)');foreach($selected as $pid)$ins->execute([$id,$pid]);audit('roles.update','role',$id,['permissions'=>count($selected)]);echo json_encode(['ok'=>true,'message'=>'Permissions saved.']);exit;
    }
    if ($action === 'role.create') {
        require_permission('roles.create');$key=slugify((string)($_POST['role_key']??''));$name=trim((string)($_POST['name']??''));$description=trim((string)($_POST['description']??''));if(!$name)throw new RuntimeException('Role name is required.');db()->prepare('INSERT INTO cms_roles(role_key,name,description,is_system) VALUES(?,?,?,0)')->execute([$key,$name,$description]);$id=(int)db()->lastInsertId();audit('roles.create','role',$id);echo json_encode(['ok'=>true,'message'=>'Role created.','id'=>$id]);exit;
    }
    if ($action === 'role.delete') {
        require_permission('roles.delete');$id=(int)($_POST['role_id']??0);$st=db()->prepare('SELECT role_key,is_system FROM cms_roles WHERE id=?');$st->execute([$id]);$role=$st->fetch();if(!$role||$role['is_system'])throw new RuntimeException('System roles are protected.');$st=db()->prepare('SELECT COUNT(*) FROM cms_user_roles WHERE role_id=?');$st->execute([$id]);if((int)$st->fetchColumn()>0)throw new RuntimeException('Remove users from this role before deleting it.');db()->prepare('DELETE FROM cms_roles WHERE id=?')->execute([$id]);audit('roles.delete','role',$id);echo json_encode(['ok'=>true,'message'=>'Role deleted.']);exit;
    }
    throw new RuntimeException('Unknown action.');
} catch (Throwable $e) { http_response_code(422); echo json_encode(['ok'=>false,'message'=>$e->getMessage()]); }
