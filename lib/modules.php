<?php
declare(strict_types=1);
function module_def(string $key): ?array { $modules=cfg('modules',[]); return $modules[$key]??null; }
function module_enabled(string $key): bool { if (!module_def($key)) return false; if (module_def($key)['system']??false) return true; try { $st=db()->prepare('SELECT is_enabled FROM cms_modules WHERE module_key=?'); $st->execute([$key]); $row=$st->fetch(); return !$row || (bool)$row['is_enabled']; } catch(Throwable) { return true; } }
function enabled_modules(): array { $out=[]; foreach (cfg('modules',[]) as $key=>$def) if (module_enabled($key)) $out[$key]=$def; return $out; }
function module_permission(string $module,string $action): string { return $module.'.'.$action; }
function field_type(mixed $definition): string { return is_array($definition) ? (string)($definition[0]??'text') : (string)$definition; }
