<?php
declare(strict_types=1);
require __DIR__.'/lib/bootstrap.php';
require __DIR__.'/lib/layout.php';

$type=preg_replace('/[^a-z_]/','',(string)($_GET['type']??'posts')) ?: 'posts';
$def=module_def($type);
if(!$def||($def['system']??false)||!isset($def['fields']['category'])){http_response_code(404);exit('This content type does not support categories.');}
require_permission('categories.read');
$error=null;
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    try{
        verify_csrf();$action=(string)($_POST['action']??'save');$id=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));
        if($action==='delete'){require_permission('categories.delete');db()->prepare('DELETE FROM cms_categories WHERE id=? AND module_key=?')->execute([$id,$type]);audit('categories.delete','category',$id,['module'=>$type]);flash('Category deleted.');}
        else{require_permission($id?'categories.update':'categories.create');if($name===''||strlen($name)>120)throw new RuntimeException('Enter a category name of up to 120 characters.');$slug=slugify($name);if($id)db()->prepare('UPDATE cms_categories SET name=?,slug=? WHERE id=? AND module_key=?')->execute([$name,$slug,$id,$type]);else db()->prepare('INSERT INTO cms_categories(module_key,name,slug,status) VALUES(?,?,?,?)')->execute([$type,$name,$slug,'active']);audit($id?'categories.update':'categories.create','category',$id?:((int)db()->lastInsertId()),['module'=>$type,'name'=>$name]);flash($id?'Category updated.':'Category created.');}
        redirect('categories.php?type='.urlencode($type));
    }catch(Throwable $e){$error=$e instanceof PDOException&&$e->getCode()==='23000'?'That category already exists.':$e->getMessage();}
}
$st=db()->prepare('SELECT * FROM cms_categories WHERE module_key=? ORDER BY sort_order,name');$st->execute([$type]);$rows=$st->fetchAll();page_start('Manage '.$def['label'].' Categories',$type);
?>
<div class="category-layout">
<section class="panel category-workspace"><div class="category-workspace-head"><div><a class="category-back" href="content.php?module=<?=e($type)?>">← Back to <?=e($def['label'])?></a><span class="eyebrow">Content organisation</span><h2><?=e($def['label'])?> categories</h2><p class="muted">Group your <?=e(strtolower($def['label']))?> so they remain easy to find and manage.</p></div><?php if(can('categories.create')):?><button class="button primary" type="button" data-open-panel="categoryPanel">+ Add category</button><?php endif;?></div><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><div class="category-list"><div class="category-list-meta"><span><?=count($rows)?> <?=count($rows)===1?'category':'categories'?></span><span>Actions</span></div><?php if(!$rows):?><div class="category-empty"><span class="category-empty-mark">◈</span><strong>No categories yet</strong><span>Create the first category for this content type.</span></div><?php endif;?><?php foreach($rows as $row):?><div class="category-row"><div class="category-row-name"><span class="category-row-mark"></span><?=e($row['name'])?></div><div class="category-actions"><?php if(can('categories.update')):?><button class="button ghost small" type="button" data-category-edit data-id="<?=e($row['id'])?>" data-name="<?=e($row['name'])?>">✎ Edit</button><?php endif;?><?php if(can('categories.delete')):?><form method="post" class="inline" onsubmit="return confirm('Delete this category?')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=e($row['id'])?>"><button class="button danger small">⌫ Delete</button></form><?php endif;?></div></div><?php endforeach;?></div></section>
<aside class="category-art" aria-hidden="true"><span>Category studio</span><strong>Clear structure.<br>Confident publishing.</strong><div class="category-art-orb"></div><div class="category-art-card">CATEGORY<br><b><?=e(strtoupper($def['label']))?></b></div></aside>
</div>
<section class="panel modal-panel" id="categoryPanel" hidden><div class="panel-head"><h3 id="categoryPanelTitle">Add category</h3><button class="button ghost" type="button" data-close-panel="categoryPanel">Cancel</button></div><p class="muted" id="categoryPanelCopy">Create a category that will be available when publishing <?=e(strtolower($def['label']))?>.</p><form method="post" id="categoryForm"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="categoryId" value="0"><label>Category name<input id="categoryName" name="name" required maxlength="120" placeholder="e.g. Environmental Monitoring"></label><div class="form-actions"><button class="button primary">Save category</button></div></form></section>
<script>
(() => { const form=document.getElementById('categoryForm'),id=document.getElementById('categoryId'),name=document.getElementById('categoryName'),title=document.getElementById('categoryPanelTitle'),copy=document.getElementById('categoryPanelCopy'),add=document.querySelector('[data-open-panel="categoryPanel"]'); add?.addEventListener('click',()=>{form.reset();id.value='0';title.textContent='Add category';copy.textContent='Create a category that will be available when publishing <?=e(strtolower($def['label']))?>.';}); document.querySelectorAll('[data-category-edit]').forEach(button=>button.addEventListener('click',()=>{add?.click();id.value=button.dataset.id;name.value=button.dataset.name;title.textContent='Edit category';copy.textContent='Update this category label. Changes are available immediately in the editor.';setTimeout(()=>name.focus(),30);})); })();
</script>
<?php page_end(); ?>
