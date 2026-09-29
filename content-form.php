<?php
declare(strict_types=1);
require __DIR__.'/lib/bootstrap.php';
require __DIR__.'/lib/content.php';
$module=(string)($_GET['module']??'');
$editId=(int)($_GET['edit']??0);
try { $def=content_definition($module); } catch (Throwable) { http_response_code(404); exit('This content module does not exist.'); }
require_permission($editId?"$module.update":"$module.create");
try { $record=$editId?content_existing($editId,$module):null; } catch (Throwable) { http_response_code(404); exit('This item no longer exists.'); }
?>
<form method="post" action="content.php?module=<?=e($module)?>" enctype="multipart/form-data" data-modal-form>
<?=csrf_field()?><input type="hidden" name="id" value="<?=e($editId)?>">
<div class="editor-layout">
    <section class="editor-main">
        <?php foreach($def['fields'] as $name=>$definition): if(!content_sidebar_field($name,$definition)): render_content_control($name,$definition,$record['data'][$name]??''); endif; endforeach; ?>
    </section>
    <aside class="editor-sidebar">
        <section class="panel"><div class="panel-head"><h3>Settings</h3></div><?php foreach($def['fields'] as $name=>$definition): if(content_sidebar_field($name,$definition)&&!in_array(field_type($definition),['image','file','gallery'],true)): render_content_control($name,$definition,$record['data'][$name]??'',true); endif; endforeach; ?></section>
        <?php foreach($def['fields'] as $name=>$definition): if(in_array(field_type($definition),['image','file','gallery'],true)): ?><section class="panel"><div class="panel-head"><h3><?=e($name==='featured_image'?'Featured Image':(field_type($definition)==='image'?'Media':ucwords(str_replace('_',' ',$name))))?></h3></div><?php render_content_control($name,$definition,$record['data'][$name]??'',true,true); ?></section><?php endif; endforeach; ?>
        <?php if($record): ?><section class="panel"><div class="panel-head"><h3>Record</h3></div><p class="record-meta"><strong>Created:</strong> <?=e($record['created_at']??'')?></p><p class="record-meta"><strong>Updated:</strong> <?=e($record['updated_at']??'')?></p><?php if(can("$module.delete")): ?><div class="record-actions"><button type="button" class="button danger" data-content-trash>Move to trash</button><small>This can be restored from Trash.</small></div><?php endif; ?></section><?php endif; ?>
    </aside>
</div>
<div class="editor-save-bar"><button class="button primary"><?=$editId?'Save changes':'Create item'?></button><button class="button ghost" type="button" data-close-modal>Cancel</button></div>
</form>
