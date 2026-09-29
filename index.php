<?php declare(strict_types=1); require __DIR__.'/lib/bootstrap.php'; require __DIR__.'/lib/layout.php'; require __DIR__.'/lib/content.php'; require_login();
ensure_content_trash_schema();
$stats=[]; foreach(enabled_modules() as $key=>$def){ if($def['system']??false) continue; try{$st=db()->prepare('SELECT COUNT(*) FROM cms_content WHERE module_key=? AND deleted_at IS NULL');$st->execute([$key]);$stats[$key]=(int)$st->fetchColumn();}catch(Throwable){$stats[$key]=0;} }
$recent=[]; try{$recent=db()->query('SELECT id,module_key,title,status,updated_at FROM cms_content WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 8')->fetchAll();}catch(Throwable){}
page_start('Dashboard','dashboard'); ?>
<div class="welcome"><span class="eyebrow">Master control panel</span><p>Everything your websites need, in one place.</p><a class="button primary" href="settings.php">Configure modules</a></div>
<div class="stat-grid dashboard-kpis">
    <?php $palette=['#3168e8','#198f86','#c46b08','#17633d','#188a84','#175a3f','#7657d8','#57708d']; $i=0; foreach(array_slice($stats,0,8,true) as $key=>$count): $accent=$palette[$i%count($palette)]; $i++; $label=(string)(module_def($key)['label']??$key); ?>
        <a class="stat-card" href="content.php?module=<?=e($key)?>" style="--accent:<?=e($accent)?>">
            <span class="stat-card-label"><?=e(strtoupper($label))?></span>
            <strong><?=number_format($count)?></strong>
            <span class="stat-card-icon"><?=icon(module_def($key)['icon']??'file')?></span>
            <span class="stat-card-wave" aria-hidden="true"></span>
        </a>
    <?php endforeach;?>
</div>
<section class="panel"><div class="panel-head"><div><h3>Recently updated</h3><p class="muted">Latest changes across enabled content modules.</p></div><a class="button ghost" href="audit.php">View audit log</a></div><?php if(!$recent):?><p class="empty">No content has been created yet.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>Title</th><th>Module</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody><?php foreach($recent as $row):?><tr><td><strong><?=e($row['title']?:'Untitled')?></strong></td><td><?=e(module_def($row['module_key'])['label']??$row['module_key'])?></td><td><span class="badge <?=e($row['status'])?>"><?=e($row['status'])?></span></td><td><?=e($row['updated_at'])?></td><td><a href="content.php?module=<?=e($row['module_key'])?>&edit=<?=e($row['id'])?>">Edit</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section><?php page_end();
