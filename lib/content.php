<?php
declare(strict_types=1);

function content_definition(string $module): array {
    ensure_content_trash_schema();
    $definition = module_def($module);
    if (!$definition || ($definition['system'] ?? false) || !module_enabled($module)) throw new RuntimeException('Module not found.');
    return $definition;
}

function ensure_content_trash_schema(): void {
    static $ready = false;
    if ($ready) return;
    $columns = db()->query('SHOW COLUMNS FROM cms_content')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('deleted_at', $columns, true)) db()->exec('ALTER TABLE cms_content ADD COLUMN deleted_at DATETIME NULL AFTER published_at');
    if (!in_array('deleted_by', $columns, true)) db()->exec('ALTER TABLE cms_content ADD COLUMN deleted_by INT NULL AFTER deleted_at');
    $index = db()->query("SHOW INDEX FROM cms_content WHERE Key_name='idx_content_trash'")->fetch();
    if (!$index) db()->exec('CREATE INDEX idx_content_trash ON cms_content (module_key, deleted_at)');
    $ready = true;
}

function content_existing(int $id, string $module, bool $includeTrashed = false): array {
    if (!$id) return [];
    $sql = 'SELECT * FROM cms_content WHERE id=? AND module_key=?' . ($includeTrashed ? '' : ' AND deleted_at IS NULL');
    $st=db()->prepare($sql);$st->execute([$id,$module]);$row=$st->fetch();
    if (!$row) throw new RuntimeException('The content item no longer exists.');
    $row['data']=json_decode($row['data'],true)?:[];return $row;
}

function content_move_to_trash(int $id, string $module): bool {
    ensure_content_trash_schema();
    $statement = db()->prepare('UPDATE cms_content SET deleted_at=NOW(), deleted_by=? WHERE id=? AND module_key=? AND deleted_at IS NULL');
    $statement->execute([(int)(current_user()['id'] ?? 0), $id, $module]);
    return $statement->rowCount() === 1;
}

function content_restore(int $id, string $module): bool {
    ensure_content_trash_schema();
    $statement = db()->prepare('UPDATE cms_content SET deleted_at=NULL, deleted_by=NULL, updated_by=? WHERE id=? AND module_key=? AND deleted_at IS NOT NULL');
    $statement->execute([(int)(current_user()['id'] ?? 0), $id, $module]);
    return $statement->rowCount() === 1;
}

function content_collect(string $module, int $id, array $fields): array {
    $existing=content_existing($id,$module);$old=$existing['data']??[];$data=[];$title='';
    foreach($fields as $name=>$definition){$type=field_type($definition);$value=$_POST[$name]??'';
        if($type==='image'){
            if(isset($_POST['remove_'.$name])&&$_POST['remove_'.$name]==='1')$value=null;
            elseif(isset($_FILES[$name])&&($_FILES[$name]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$value=store_upload($_FILES[$name]);
            elseif(trim((string)($_POST[$name]??''))!=='')$value=trim((string)$_POST[$name]);
            else $value=$old[$name]??null;
        }elseif($type==='gallery'){
            $selected=$_POST[$name]??[];
            $value=is_array($selected)?array_values(array_filter($selected,'is_string')):(is_array($old[$name]??null)?$old[$name]:[]);
            if(isset($_FILES[$name]['name'])&&is_array($_FILES[$name]['name']))foreach(array_keys($_FILES[$name]['name']) as $index){$single=['name'=>$_FILES[$name]['name'][$index],'type'=>$_FILES[$name]['type'][$index],'tmp_name'=>$_FILES[$name]['tmp_name'][$index],'error'=>$_FILES[$name]['error'][$index],'size'=>$_FILES[$name]['size'][$index]];if($single['error']!==UPLOAD_ERR_NO_FILE)$value[]=store_upload($single);}
            $value=array_values(array_unique(array_filter($value,'is_string')));
        }elseif($type==='json'){$value=json_decode((string)$value,true);if(json_last_error()!==JSON_ERROR_NONE)throw new RuntimeException("$name must contain valid JSON.");}
        elseif($type==='richtext')$value=clean_html((string)$value);
        else $value=is_string($value)?trim($value):$value;
        if($type==='slug')$value=slugify((string)($value?:($_POST[$definition['from']??'title']??$title)));
        if(in_array($name,['title','name','question'],true))$title=(string)$value;
        if($type==='status'&&$value==='')$value='draft';$data[$name]=$value;
    }
    if($title===''){
        $title=(string)($data['title']??$data['name']??$data['question']??'');
        if($title===''){
            foreach($fields as $fallbackName=>$fallbackDefinition){
                if(field_type($fallbackDefinition)==='text'&&trim((string)($data[$fallbackName]??''))!==''){$title=(string)$data[$fallbackName];break;}
            }
        }
        if($title==='')$title='Untitled';
    }
    $data['title']=$title;$slug=slugify((string)($data['slug']??$title));
    $st=db()->prepare('SELECT id FROM cms_content WHERE module_key=? AND slug=? AND id<>? LIMIT 1');$st->execute([$module,$slug,$id]);if($st->fetch())$slug.='-'.substr(bin2hex(random_bytes(3)),0,6);$data['slug']=$slug;
    return ['data'=>$data,'title'=>$title,'slug'=>$slug,'status'=>(string)($data['status']??'draft'),'existing'=>$existing];
}

function content_save(string $module,int $id,array $fields): int {
    $item=content_collect($module,$id,$fields);$json=json_encode($item['data'],JSON_UNESCAPED_SLASHES);$user=(int)current_user()['id'];
    if($id){db()->prepare('UPDATE cms_content SET title=?,slug=?,status=?,data=?,updated_by=? WHERE id=? AND module_key=?')->execute([$item['title'],$item['slug'],$item['status'],$json,$user,$id,$module]);$saved=$id;}
    else{db()->prepare('INSERT INTO cms_content(module_key,title,slug,status,data,created_by,updated_by,published_at) VALUES(?,?,?,?,?,?,?,?)')->execute([$module,$item['title'],$item['slug'],$item['status'],$json,$user,$user,$item['status']==='published'?date('Y-m-d H:i:s'):null]);$saved=(int)db()->lastInsertId();}
    return $saved;
}

function content_sidebar_field(string $name, mixed $definition): bool {
    $type=field_type($definition);
    return in_array($name,['status','category','published_at','sort_order','featured','year','client','location','type','website'],true)||in_array($type,['image','file','icon'],true);
}

function managed_category_options(string $module): array {
    $st=db()->prepare('SELECT name FROM cms_categories WHERE module_key=? AND status=? ORDER BY sort_order,name');
    $st->execute([$module,'active']);
    return array_map(static fn(array $row): string => (string)$row['name'], $st->fetchAll());
}

function media_picker_items(): array {
    static $items;
    if (is_array($items)) return $items;
    $st=db()->query("SELECT stored_name,file_name,alt_text FROM cms_media WHERE mime_type LIKE 'image/%' ORDER BY created_at DESC LIMIT 60");
    return $items=$st->fetchAll();
}

function render_content_control(string $name, mixed $definition, mixed $value, bool $compact=false, bool $hide_label=false): void {
    $type=field_type($definition);$label=ucwords(str_replace('_',' ',$name));$placeholder='Enter '.strtolower($label);
    if(is_array($value)&&$type!=='gallery')$value=json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    echo '<div class="content-field'.($compact?' content-field--compact':'').'">'.($hide_label?'':'<label>'.e($label));
    if(in_array($type,['richtext','textarea','json'],true))echo '<textarea name="'.e($name).'" placeholder="'.e($placeholder).'" rows="'.($type==='richtext'?8:4).'" '.($type==='json'?'class="code"':'').'>'.e((string)$value).'</textarea>';
    elseif($type==='status')echo '<select name="'.e($name).'">'.'<option value="draft" '.($value==='draft'?'selected':'').'>Draft</option><option value="published" '.($value==='published'?'selected':'').'>Published</option><option value="archived" '.($value==='archived'?'selected':'').'>Archived</option></select>';
    elseif(in_array($type,['select','category'],true)){$options=$type==='category'?managed_category_options((string)($definition[1]??$name)):($definition[1]??[]);$html='<select name="'.e($name).'"'.($compact?' class="select-compact"':'').'><option value="">Choose '.e(strtolower($label)).'</option>';foreach($options as $option)$html.='<option value="'.e($option).'" '.((string)$value===(string)$option?'selected':'').'>'.e($option).'</option>';echo $html.'</select>';}
    elseif($type==='icon'){$glyphs=['leaf'=>'♧','shield'=>'◆','briefcase'=>'▣','globe'=>'◎','flask'=>'⚗','hard-hat'=>'⛑','water'=>'≈','recycle'=>'♻'];$html='<div class="icon-picker" data-icon-picker><input type="hidden" name="'.e($name).'" value="'.e((string)$value).'">';foreach(($definition[1]??[]) as $option)$html.='<button type="button" class="icon-option'.((string)$value===(string)$option?' is-selected':'').'" data-icon-value="'.e($option).'" title="'.e(ucwords(str_replace('-',' ',$option))).'"><span class="icon-option-mark">'.e($glyphs[$option]??'•').'</span><span>'.e(ucwords(str_replace('-',' ',$option))).'</span></button>';echo $html.'</div>' ;}
    elseif(in_array($type,['image','file','gallery'],true)){
        $items=$type==='file'?[]:media_picker_items();$values=$type==='gallery'?(is_array($value)?$value:[]):(($value&&!is_array($value))?[(string)$value]:[]);
        echo '<div class="media-picker" data-media-picker data-media-mode="'.e($type).'" data-media-name="'.e($name).'">';
        if($type==='gallery'){echo '<div class="media-picker-selected'.($values?'':' is-empty').'" data-media-selected>';if(!$values)echo '<span class="media-picker-selected-empty" data-media-empty-hint>No images selected yet</span>';foreach($values as $selected)echo '<span class="selected-media-chip"><img src="'.e(media_url((string)$selected)).'" alt=""><input type="hidden" name="'.e($name).'[]" value="'.e((string)$selected).'" data-media-value></span>';echo '</div>';}else echo '<input type="hidden" name="'.e($name).'" value="'.e((string)($values[0]??'' )).'" data-media-value>';
        if($type==='image'&&$values)echo '<div class="content-image-preview" data-media-preview><img src="'.e(media_url($values[0])).'" alt=""></div>';
        if($type!=='file'){echo '<button type="button" class="media-picker-trigger" data-media-open><span class="media-picker-trigger-icon">'.ui_icon_svg('gallery').'</span><span>Choose from media library</span></button><div class="media-picker-menu" data-media-menu hidden>';if(!$items)echo '<p class="media-picker-empty">No media uploaded yet. Use the upload button below or open the <a href="media.php">Media Library</a>.</p>';foreach($items as $item)echo '<button type="button" class="media-picker-option" data-media-select="'.e($item['stored_name']).'" data-media-src="'.e(media_url($item['stored_name'])).'"><img src="'.e(media_url($item['stored_name'])).'" alt="'.e($item['alt_text']?:$item['file_name']).'"><span>'.e($item['file_name']).'</span></button>';echo '</div>';} 
        echo '<label class="media-upload-label media-dropzone" data-dropzone><span class="media-dropzone-title">'.($type==='gallery'?'Drop images here or choose files':'Drop an image here or choose a file').'</span><span class="media-file-button">Choose '.($type==='gallery'?'files':'file').'</span><input class="media-input" data-drop-input type="file" aria-label="'.e($label).'" name="'.e($name).($type==='gallery'?'[]':'').'" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" '.($type==='gallery'?'multiple':'').'></label>';
        if($type==='image'&&$values)echo '<label class="media-remove"><input type="checkbox" name="remove_'.e($name).'" value="1" data-media-remove> Remove current image</label>';
        echo '<small class="media-help">JPG, PNG, WEBP or GIF · up to 10MB</small></div>';
    }
    else echo '<input type="'.(in_array($type,['email','url','number','date'],true)?$type:'text').'" name="'.e($name).'" value="'.e((string)$value).'" placeholder="'.e($placeholder).'">';
    echo ($hide_label?'':'</label>').'</div>';
}
