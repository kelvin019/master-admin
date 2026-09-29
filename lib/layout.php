<?php
declare(strict_types=1);

function icon(string $name): string {
    $map=['grid'=>'▦','file'=>'▤','edit'=>'✎','briefcase'=>'▣','layers'=>'▥','users'=>'♙','quote'=>'❝','help'=>'?','award'=>'✦','tool'=>'⚒','folder'=>'▰','menu'=>'☰','form'=>'▤','mail'=>'✉','send'=>'➤','corner'=>'↪','settings'=>'⚙','shield'=>'◆','clock'=>'◷','archive'=>'▤','home'=>'⌂','logout'=>'↪'];
    if ($name === 'image') return '<span class="icon" aria-hidden="true">'.ui_icon_svg('gallery').'</span>';
    return '<span class="icon" aria-hidden="true">'.($map[$name]??'•').'</span>';
}

function page_start(string $title,string $active=''): void {
    require_login();
    $flash=flash();
    $contentCounts=[];
    foreach (db()->query('SELECT module_key, COUNT(*) AS total FROM cms_content WHERE deleted_at IS NULL GROUP BY module_key')->fetchAll() as $count) $contentCounts[$count['module_key']] = (int)$count['total'];
    $assetVersion=(string)max((int)@filemtime(MASTER_CMS_ROOT.'/assets/admin.css'),(int)@filemtime(MASTER_CMS_ROOT.'/assets/admin.js'));
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · <?=e(cfg('admin.title'))?></title><meta name="robots" content="noindex,nofollow"><link rel="stylesheet" href="assets/admin.css?v=<?=e($assetVersion)?>"></head><body><div class="app"><aside class="sidebar"><a class="brand" href="index.php"><span class="brand-mark">MC</span><span><strong>Master CMS</strong><small>Administration</small></span></a><nav><a class="nav-link <?=$active==='dashboard'?'active':''?>" href="index.php"><?=icon('home')?>Dashboard</a><?php
    $groups=[];
    foreach(enabled_modules() as $key=>$def) if($key!=='categories') $groups[$def['group']??'Other'][$key]=$def;
    foreach($groups as $group=>$mods):
    ?><div class="nav-heading"><?=e($group)?></div><?php foreach($mods as $key=>$def):
        if($key==='audit'&&!can('audit.read')) continue;
        if($key==='users'&&!can('users.read')) continue;
        if(!($def['system']??false)&&!can("$key.read")) continue;
        $hasCategories=isset($def['fields']['category']);
        $href=in_array($key,['users','roles','audit','media','settings','mail','backups'],true)?$key.'.php':'content.php?module='.$key;
        $isCategoryPage=($active===$key)&&basename((string)($_SERVER['PHP_SELF']??''))==='categories.php';
        $isActive=($active===$key)&&!$isCategoryPage;
        $expanded=$isActive||$isCategoryPage;
        ?><div class="nav-entry <?=$hasCategories?'has-children':''?>"><div class="nav-parent <?=$isActive||$isCategoryPage?'is-active':''?>"><a class="nav-link <?=$isActive||$isCategoryPage?'active':''?>" href="<?=e($href)?>"><?=icon($def['icon']??'file')?><span><?=e($def['label'])?></span><?php if(!($def['system']??false)):?><b class="nav-count"><?=e($contentCounts[$key]??0)?></b><?php endif;?></a></div><?php if($hasCategories&&$expanded): ?><div class="nav-children"><a class="<?=$isActive?'active':''?>" href="<?=e($href)?>">All <?=e($def['label'])?></a><a class="<?=$isCategoryPage?'active':''?>" href="categories.php?type=<?=e($key)?>">Manage categories</a></div><?php endif; ?></div><?php
    endforeach; endforeach;
    ?><div class="nav-heading">Account</div><a class="nav-link" href="account.php"><?=icon('shield')?>My account</a><a class="nav-link" href="logout.php"><?=icon('logout')?>Sign out</a></nav></aside><section class="main"><header class="topbar"><button class="mobile-menu" type="button" onclick="document.body.classList.toggle('menu-open')">☰</button><h1><?=e($title)?></h1><div class="top-user"><?=e(current_user()['display_name']??'Administrator')?></div></header><main class="content"><?php if($flash):?><div class="alert <?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endif;?><?php
}

function page_end(): void { $assetVersion=(string)max((int)@filemtime(MASTER_CMS_ROOT.'/assets/admin.css'),(int)@filemtime(MASTER_CMS_ROOT.'/assets/admin.js')); ?> </main></section></div><script src="assets/admin.js?v=<?=e($assetVersion)?>" defer></script></body></html><?php }
