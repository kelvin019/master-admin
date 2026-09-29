<?php declare(strict_types=1); require __DIR__.'/lib/bootstrap.php'; require __DIR__.'/lib/layout.php'; require_permission('backups.read');
function backups_find_mysqldump(): ?string {
    $configured = (string)cfg('admin.mysqldump_path', '');
    if ($configured !== '' && is_executable($configured)) return $configured;
    $candidates = ['/usr/local/bin/mysqldump', '/usr/bin/mysqldump', '/opt/homebrew/bin/mysqldump'];
    foreach (glob('/Applications/*/apps/mysql*/bin/mysqldump') ?: [] as $path) $candidates[] = $path;
    foreach (glob('/Applications/*/mysql*/bin/mysqldump') ?: [] as $path) $candidates[] = $path;
    foreach ($candidates as $candidate) if (is_executable($candidate)) return $candidate;
    $which = trim((string)shell_exec('command -v mysqldump 2>/dev/null'));
    return $which !== '' && is_executable($which) ? $which : null;
}
$files=glob(MASTER_CMS_ROOT.'/storage/*.sql')?:[];
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    verify_csrf();require_permission('backups.create');
    $binary=backups_find_mysqldump();
    if($binary===null){flash('Could not locate the mysqldump command. Set MASTER_CMS_MYSQLDUMP_PATH to its full path.','error');redirect('backups.php');}
    $file=MASTER_CMS_ROOT.'/storage/backup-'.date('Ymd-His').'.sql';$c=cfg('db');
    $errorFile=$file.'.err';
    $command=sprintf('MYSQL_PWD=%s %s --host=%s --port=%d --user=%s --single-transaction --routines --events %s > %s 2> %s',escapeshellarg($c['pass']),escapeshellarg($binary),escapeshellarg($c['host']),$c['port'],escapeshellarg($c['user']),escapeshellarg($c['name']),escapeshellarg($file),escapeshellarg($errorFile));
    exec($command,$output,$exit);
    $stderr=is_file($errorFile)?trim((string)file_get_contents($errorFile)):'';
    if(is_file($errorFile))unlink($errorFile);
    if($exit!==0||!is_file($file)||filesize($file)===0){if(is_file($file))unlink($file);flash('The database backup command failed: '.($stderr?:'unknown error'),'error');}
    else{audit('backups.create','backup');flash('Database backup created.');}
    redirect('backups.php');
}
page_start('Backups','backups');?><div class="page-head"><div><span class="eyebrow">SYSTEM</span><h2>Database backups</h2><p class="muted">Create local SQL snapshots for recovery. Keep production backups outside the public web root.</p></div></div><section class="panel"><form method="post"><?=csrf_field()?><button class="button primary">Create database backup</button></form></section><section class="panel"><h3>Available snapshots</h3><?php if(!$files):?><p class="empty">No backups have been created.</p><?php else:?><ul class="file-list"><?php foreach($files as $file):?><li><code><?=e(basename($file))?></code><span><?=number_format(filesize($file)/1024,1)?> KB</span></li><?php endforeach;?></ul><?php endif;?></section><?php page_end();
