<?php
declare(strict_types=1);
require __DIR__.'/lib/bootstrap.php';
require __DIR__.'/lib/layout.php';
require_permission('media.read');

$error=null;
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    try{
        verify_csrf();require_permission('media.create');
        if(empty($_FILES['file']))throw new RuntimeException('Choose at least one file.');
        $files=[];
        if(is_array($_FILES['file']['name']??null))foreach(array_keys($_FILES['file']['name']) as $index)$files[]=['name'=>$_FILES['file']['name'][$index],'type'=>$_FILES['file']['type'][$index],'tmp_name'=>$_FILES['file']['tmp_name'][$index],'error'=>$_FILES['file']['error'][$index],'size'=>$_FILES['file']['size'][$index]];
        else $files[]=$_FILES['file'];
        $uploaded=0;$alt=trim((string)($_POST['alt_text']??''));
        foreach($files as $file){$stored=store_upload($file);$id=(int)db()->lastInsertId();db()->prepare('UPDATE cms_media SET alt_text=? WHERE id=?')->execute([$alt,$id]);audit('media.create','media',$id);$uploaded++;}
        flash($uploaded.' media file'.($uploaded===1?'':'s').' uploaded.');redirect('media.php');
    }catch(Throwable $e){$error=$e->getMessage();}
}
$rows=db()->query('SELECT * FROM cms_media ORDER BY created_at DESC LIMIT 100')->fetchAll();
page_start('Media Library','media');
?>
<div class="page-head"><div><span class="eyebrow">SYSTEM</span><h2>Media library</h2><p class="muted">Centralized images and documents for every content module.</p></div><?php if(can('media.create')): ?><button class="button primary" type="button" data-open-panel="mediaUploadPanel">+ Upload media</button><?php endif; ?></div>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<section class="panel modal-panel" id="mediaUploadPanel" hidden><div class="panel-head"><h3>Upload media</h3><button class="button ghost" type="button" data-close-panel="mediaUploadPanel">Cancel</button></div><p class="muted">Drag and drop one or more images, or choose files from your computer.</p><form method="post" action="media.php" enctype="multipart/form-data" class="form-grid" data-ajax-form data-ajax-action="media.upload" data-reload="true"><?=csrf_field()?><label class="media-dropzone" data-dropzone><span class="media-dropzone-title">Drop files here or click to browse</span><span class="media-file-button">Choose files</span><input type="file" name="file[]" data-drop-input accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" multiple required><small data-drop-label>JPG, PNG, WEBP, GIF or PDF · up to 10MB each</small></label><label>Alt text<input name="alt_text" placeholder="Describe the image for accessibility"></label><div><button class="button primary">Upload media</button></div></form></section>
<?php if(!$rows): ?>
<section class="panel media-empty-state"><div class="media-empty-icon">▧</div><h3>Your media library is empty</h3><p>Upload images and documents here, then select them from any featured image, team photo, service image or gallery field.</p><?php if(can('media.create')): ?><button class="button primary" type="button" data-open-panel="mediaUploadPanel">Upload your first file</button><?php endif; ?><small>JPG, PNG, WEBP, GIF or PDF · up to 10MB per file</small></section>
<?php else: ?>
<section class="panel"><div class="media-library-toolbar"><span><?=count($rows)?> media items</span><?php if(can('media.delete')): ?><label class="check"><input type="checkbox" data-select-all> Select all</label><div class="bulk-toolbar" data-bulk-toolbar data-bulk-action="media.bulk_delete" data-bulk-ids-name="ids[]" hidden><span><strong data-selected-count>0</strong> selected</span><button class="button danger small" type="button" data-bulk-delete>⌫ Delete selected</button></div><?php endif; ?></div><div class="media-grid"><?php foreach($rows as $row):?><article class="media-card" data-content-row><?php if(can('media.delete')): ?><label class="media-select"><input type="checkbox" data-row-select value="<?=e($row['id'])?>" aria-label="Select <?=e($row['file_name'])?>"></label><?php endif; ?><div class="media-preview"><?php if(str_starts_with($row['mime_type'],'image/')):?><img src="uploads/<?=e($row['stored_name'])?>" alt="<?=e($row['alt_text'])?>"><?php else:?><span><?=icon('file')?><?=e(strtoupper(pathinfo($row['file_name'],PATHINFO_EXTENSION)))?></span><?php endif;?></div><strong><?=e($row['file_name'])?></strong><small><?=number_format((int)$row['file_size']/1024,1)?> KB · <?=e($row['mime_type'])?></small></article><?php endforeach;?></div></section>
<?php endif; page_end();
