<?php
declare(strict_types=1);
require __DIR__.'/lib/bootstrap.php';
require __DIR__.'/lib/layout.php';
require __DIR__.'/lib/content.php';

$module=(string)($_GET['module']??'');
try { $def=content_definition($module); } catch (Throwable) { http_response_code(404); exit('This content module does not exist.'); }
require_permission("$module.read");
$fields=$def['fields'];
$editId=(int)($_GET['edit']??0);
$trashView=(string)($_GET['trash']??'')==='1';
if ($trashView) $editId=0;
$editorOpen=$editId>0||isset($_GET['edit']);
$error=null;

if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    try{
        verify_csrf();
        $action=(string)($_POST['action']??'');
        $id=(int)($_POST['id']??0);
        if($action==='delete'||$action==='trash'){
            require_permission("$module.delete");
            if (!content_move_to_trash($id,$module)) throw new RuntimeException('This item is already in trash or no longer exists.');
            audit("$module.trash",$module,$id);
            flash($def['label'].' item moved to trash.');
            redirect('content.php?module='.urlencode($module));
        }
        if($action==='restore'){
            require_permission("$module.update");
            if (!content_restore($id,$module)) throw new RuntimeException('This item is no longer in trash.');
            audit("$module.restore",$module,$id);
            flash($def['label'].' item restored.');
            redirect('content.php?module='.urlencode($module).'&trash=1');
        }
        require_permission($id?"$module.update":"$module.create");
        $saved=content_save($module,$id,$fields);
        audit($id?"$module.update":"$module.create",$module,$saved);
        if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest'){
            header('Content-Type: application/json');
            echo json_encode(['ok'=>true,'message'=>$def['label'].' saved.','id'=>$saved]);
            exit;
        }
        flash($def['label'].' saved.');
        redirect('content.php?module='.urlencode($module));
    }catch(Throwable $e){
        $error=$e->getMessage();
        if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest'){
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
            exit;
        }
    }
}

try { $record=$editId?content_existing($editId,$module):null; } catch (Throwable) { flash('That item no longer exists.','error'); redirect('content.php?module='.urlencode($module)); }
$q=trim((string)($_GET['q']??''));
$categoryFilter=trim((string)($_GET['category']??''));
$sql='SELECT * FROM cms_content WHERE module_key=? AND '.($trashView?'deleted_at IS NOT NULL':'deleted_at IS NULL');
$params=[$module];
if($q!==''){$sql.=' AND (title LIKE ? OR data LIKE ?)';$params[]="%$q%";$params[]="%$q%";}
$categoryOptions=isset($fields['category'])?managed_category_options((string)($fields['category'][1]??$module)):[];
if($categoryFilter!==''&&isset($fields['category'])){$sql.=" AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.category'))=?";$params[]=$categoryFilter;}
$sql.=' ORDER BY updated_at DESC LIMIT 100';
$st=db()->prepare($sql);$st->execute($params);$rows=$st->fetchAll();
$trashCountStatement=db()->prepare('SELECT COUNT(*) FROM cms_content WHERE module_key=? AND deleted_at IS NOT NULL');$trashCountStatement->execute([$module]);$trashCount=(int)$trashCountStatement->fetchColumn();
$thumbnailField=null;foreach($fields as $fieldName=>$fieldDefinition){if(field_type($fieldDefinition)==='image'){$thumbnailField=$fieldName;break;}}
page_start($def['label'],$module);
?>
<div class="page-head">
    <div><span class="eyebrow"><?=e($def['group']??'Content')?></span><h2><?=e($def['label'])?><?=$trashView?' trash':''?></h2></div>
    <div class="page-actions">
        <?php if($trashView): ?><a class="button ghost" href="content.php?module=<?=e($module)?>">← Back to <?=e($def['label'])?></a>
        <?php elseif(can("$module.delete")): ?><a class="button ghost" href="content.php?module=<?=e($module)?>&trash=1"><span class="button-icon"><?=ui_icon_svg('trash')?></span>Trash<?php if($trashCount): ?> <span class="button-count"><?=e($trashCount)?></span><?php endif; ?></a><?php endif; ?>
        <?php if(!$trashView&&!$editorOpen&&can("$module.create")): ?><a class="button primary" href="content.php?module=<?=e($module)?>&edit=0#editor">+ Add <?=e(rtrim($def['label'],'s'))?></a><?php endif; ?>
    </div>
</div>
<?php if($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
<?php if($editorOpen): ?>
<form method="post" enctype="multipart/form-data" data-content-form id="contentEditorForm">
    <?=csrf_field()?><input type="hidden" name="id" value="<?=e($record['id']??0)?>">
    <div class="editor-layout" id="editor">
        <section class="panel editor-main">
            <div class="panel-head"><h3><?=$record?'Edit':'Create'?> <?=e(rtrim($def['label'],'s'))?></h3><a href="content.php?module=<?=e($module)?>">Close</a></div>
            <?php foreach($fields as $name=>$definition): if(!content_sidebar_field($name,$definition)): render_content_control($name,$definition,$record['data'][$name]??''); endif; endforeach; ?>
            <div class="editor-save-bar"><button class="button primary"><?=$record?'Save changes':'Create item'?></button><a class="button ghost" href="content.php?module=<?=e($module)?>">Cancel</a></div>
        </section>
        <aside class="editor-sidebar">
            <section class="panel"><div class="panel-head"><h3>Settings</h3></div>
                <?php foreach($fields as $name=>$definition): if(content_sidebar_field($name,$definition)&&!in_array(field_type($definition),['image','file','gallery'],true)): render_content_control($name,$definition,$record['data'][$name]??'',true); endif; endforeach; ?>
            </section>
            <?php foreach($fields as $name=>$definition): if(in_array(field_type($definition),['image','file'],true)): ?>
                <section class="panel"><div class="panel-head"><h3><?=e($name==='featured_image'?'Featured Image':(field_type($definition)==='image'?'Media':ucwords(str_replace('_',' ',$name))))?></h3></div><?php render_content_control($name,$definition,$record['data'][$name]??'',true,true); ?></section>
            <?php endif; endforeach; ?>
            <?php if($record): ?><section class="panel"><div class="panel-head"><h3>Record</h3></div><p class="record-meta"><strong>Created:</strong> <?=e($record['created_at']??'')?></p><p class="record-meta"><strong>Updated:</strong> <?=e($record['updated_at']??'')?></p><?php if(can("$module.delete")): ?><div class="record-actions"><button type="button" class="button danger" data-content-trash>Move to trash</button><small>This can be restored from Trash.</small></div><?php endif; ?></section><?php endif; ?>
        </aside>
    </div>
</form>
<?php else: ?>
<section class="panel"><form class="toolbar" method="get"><input type="hidden" name="module" value="<?=e($module)?>"><?php if($trashView): ?><input type="hidden" name="trash" value="1"><?php endif; ?><input name="q" value="<?=e($q)?>" placeholder="Search <?=e(strtolower($def['label']))?>..."><?php if($categoryOptions): ?><select name="category" aria-label="Filter by category"><option value="">All categories</option><?php foreach($categoryOptions as $option): ?><option value="<?=e($option)?>" <?=$categoryFilter===$option?'selected':''?>><?=e($option)?></option><?php endforeach; ?></select><?php endif; ?><button class="button ghost">Search</button><?php if($q!==''||$categoryFilter!==''): ?><a class="button ghost" href="content.php?module=<?=e($module)?><?=$trashView?'&trash=1':''?>">Clear</a><?php endif; ?></form>
<?php if(!$trashView&&can("$module.delete")): ?><div class="bulk-toolbar" data-bulk-toolbar hidden><span><strong data-selected-count>0</strong> selected</span><button class="button danger small" type="button" data-bulk-delete><span class="button-icon"><?=ui_icon_svg('trash')?></span>Move selected to trash</button></div><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th class="select-col"><?php if(!$trashView&&can("$module.delete")): ?><input type="checkbox" data-select-all aria-label="Select all <?=e(strtolower($def['label']))?>"><?php endif; ?></th><th>#</th><?php if($thumbnailField):?><th class="thumbnail-col">Image</th><?php endif;?><th>Title</th><th>Status</th><th><?=$trashView?'Deleted':'Updated'?></th><th class="actions">Actions</th></tr></thead><tbody>
<?php foreach($rows as $index=>$row): $rowData=json_decode((string)$row['data'],true)?:[];$thumbnail=$thumbnailField?(string)($rowData[$thumbnailField]??''):''; ?><tr data-content-row><td class="select-col"><?php if(!$trashView&&can("$module.delete")): ?><input type="checkbox" data-row-select value="<?=e($row['id'])?>" aria-label="Select <?=e($row['title']?:'Untitled')?>"><?php endif; ?></td><td class="serial"><?=e($index+1)?></td><?php if($thumbnailField):?><td class="thumbnail-col"><?php if($thumbnail):?><img class="content-table-thumbnail" src="<?=e(media_url($thumbnail))?>" alt=""><?php else:?><span class="content-table-thumbnail empty-thumbnail"><?=ui_icon_svg('gallery')?></span><?php endif;?></td><?php endif;?><td><strong><?=e($row['title']?:'Untitled')?></strong><small class="table-sub">/<?=e($row['slug'])?></small></td><td><span class="badge <?=e($row['status'])?>"><?=e($row['status'])?></span></td><td><?=e($trashView?($row['deleted_at']??''):$row['updated_at'])?></td><td class="actions action-buttons"><?php if($trashView&&can("$module.update")): ?><form method="post" class="inline"><?=csrf_field()?><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?=e($row['id'])?>"><button class="table-action edit" title="Restore <?=e($row['title']?:'item')?>"><span aria-hidden="true">↶</span><span>Restore</span></button></form><?php elseif(!$trashView): ?><?php if(can("$module.update")): ?><a class="table-action edit" href="content.php?module=<?=e($module)?>&edit=<?=e($row['id'])?>" title="Edit <?=e($row['title']?:'item')?>"><span aria-hidden="true">✎</span><span>Edit</span></a><?php endif; ?> <?php if(can("$module.delete")): ?><form method="post" class="inline" data-entity="content" data-confirm="Move this item to trash? You can restore it later."><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=e($row['id'])?>"><button class="table-action delete" title="Move <?=e($row['title']?:'item')?> to trash"><span class="button-icon"><?=ui_icon_svg('trash')?></span><span>Trash</span></button></form><?php endif; ?><?php endif; ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="<?=e($thumbnailField?7:6)?>" class="empty">No items found.</td></tr><?php endif; ?></tbody></table></div></section>
<?php endif; page_end();
