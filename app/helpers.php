<?php
declare(strict_types=1);
function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function app_name(): string { return (string)config('app.name','Team Workspace'); }
function app_version(): string { return (string)config('app.version','1.0.0'); }
function base_path(): string { $base=(string)config('app.base_path',''); $base='/'.trim($base,'/'); return $base==='/'?'':$base; }
function url(string $path=''): string { $base=base_path(); $path='/'.ltrim($path,'/'); return $base.($path==='/'?'/':$path); }
function absolute_url(string $path=''): string { return rtrim((string)config('app.url',''),' /').url($path); }

function csp_nonce(): string {
    static $nonce=null;
    if($nonce===null) $nonce=base64_encode(random_bytes(18));
    return $nonce;
}
function security_headers(): void {
    if(headers_sent()) return;
    $nonce=csp_nonce();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header_remove('X-Powered-By');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    $configuredUrl=(string)config('app.url','');
    $httpsRequest=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off');
    if($httpsRequest || strtolower((string)parse_url($configuredUrl,PHP_URL_SCHEME))==='https') {
        header('Strict-Transport-Security: max-age=31536000');
    }
}
function no_store_headers(): void {
    if(!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
function client_ip(): string {
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');
    return substr($ip,0,45);
}
function rate_limit_key(string $bucket,string $subject=''): string {
    return hash('sha256',$bucket.'|'.client_ip().'|'.mb_strtolower(trim($subject),'UTF-8'));
}
function rate_limit_retry_after(string $bucket,string $subject,int $maxAttempts,int $windowSeconds): int {
    try {
        $key=rate_limit_key($bucket,$subject);
        $st=db()->prepare('SELECT attempts,window_started_at FROM auth_rate_limits WHERE key_hash=?');
        $st->execute([$key]);$row=$st->fetch();if(!$row)return 0;
        $started=strtotime((string)$row['window_started_at']);
        if(!$started || $started<=time()-$windowSeconds){db()->prepare('DELETE FROM auth_rate_limits WHERE key_hash=?')->execute([$key]);return 0;}
        if((int)$row['attempts']<$maxAttempts)return 0;
        return max(1,$windowSeconds-(time()-$started));
    } catch(Throwable) { return 0; }
}
function rate_limit_hit(string $bucket,string $subject,int $windowSeconds): void {
    try {
        $key=rate_limit_key($bucket,$subject);$windowSeconds=max(1,$windowSeconds);
        $sql="INSERT INTO auth_rate_limits(key_hash,attempts,window_started_at,last_attempt_at) VALUES (?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE attempts=IF(window_started_at<=NOW()-INTERVAL {$windowSeconds} SECOND,1,attempts+1),window_started_at=IF(window_started_at<=NOW()-INTERVAL {$windowSeconds} SECOND,NOW(),window_started_at),last_attempt_at=NOW()";
        db()->prepare($sql)->execute([$key]);
    } catch(Throwable) {}
}
function rate_limit_clear(string $bucket,string $subject): void {
    try { db()->prepare('DELETE FROM auth_rate_limits WHERE key_hash=?')->execute([rate_limit_key($bucket,$subject)]); } catch(Throwable) {}
}
function validate_text_length(string $value,int $max,string $field): void {
    if(mb_strlen($value,'UTF-8')>$max) json_response(['ok'=>false,'error'=>$field.' слишком длинное'],422);
}
function normalize_id_list(mixed $value,int $maxItems=50): array {
    if(!is_array($value)) return [];
    $ids=array_values(array_unique(array_filter(array_map('intval',$value),static fn($v)=>$v>0)));
    if(count($ids)>$maxItems) json_response(['ok'=>false,'error'=>'Слишком много элементов в запросе'],422);
    return $ids;
}
function json_for_script(mixed $value): string {
    return (string)json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
}
function request_path(): string { $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH) ?: '/'; $base=base_path(); if($base!=='' && ($path===$base || str_starts_with($path,$base.'/'))) { $path=substr($path,strlen($base)) ?: '/'; } return '/'.ltrim($path,'/'); }
function is_api_request(): bool { return str_starts_with(request_path(),'/api/'); }
function redirect(string $path): never { header('Location: '.url($path)); exit; }
function json_response(array $data, int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store, max-age=0'); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function body_json(): array {
    $max=(int)config('security.max_json_body_bytes',1048576);
    $len=(int)($_SERVER['CONTENT_LENGTH']??0);
    if($len>$max) json_response(['ok'=>false,'error'=>'Request body too large'],413);
    $raw=file_get_contents('php://input',false,null,0,$max+1);
    if($raw===false) json_response(['ok'=>false,'error'=>'Unable to read request body'],400);
    if(strlen($raw)>$max) json_response(['ok'=>false,'error'=>'Request body too large'],413);
    if($raw==='') return [];
    try{$d=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}catch(JsonException){json_response(['ok'=>false,'error'=>'Invalid JSON'],400);}
    if(!is_array($d)) json_response(['ok'=>false,'error'=>'JSON object expected'],400);
    return $d;
}
function csrf_token(): string { no_store_headers(); if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function rotate_csrf_token(): string { return $_SESSION['csrf']=bin2hex(random_bytes(32)); }
function destroy_session(): void {
    $_SESSION=[];
    if(ini_get('session.use_cookies')){ $p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']); }
    if(session_status()===PHP_SESSION_ACTIVE)session_destroy();
}
function verify_csrf(?string $token=null): void { $token=$token ?? ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)); if(!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) json_response(['ok'=>false,'error'=>'CSRF validation failed'],419); }
function current_user(): ?array { if(empty($_SESSION['uid'])) return null; static $u=false; if($u!==false) return $u?:null; $st=db()->prepare('SELECT id,login,role,is_active,created_at,last_login_at FROM users WHERE id=? LIMIT 1'); $st->execute([$_SESSION['uid']]); $u=$st->fetch(); if(!$u || !$u['is_active']) { destroy_session(); return null; } touch_user_activity((int)$u['id']); return $u; }
function require_auth(): array { $u=current_user(); if(!$u) { if(is_api_request()) json_response(['ok'=>false,'error'=>'Unauthorized'],401); redirect('/login'); } if(!empty($_SESSION['passkey_setup_required'])&&!in_array(request_path(),['/profile','/api/passkeys/register/options','/api/passkeys/register','/logout'],true)){if(is_api_request())json_response(['ok'=>false,'error'=>'Сначала добавьте Passkey.'],403);redirect('/profile');} no_store_headers(); return $u; }
function role_rank(string $r): int { return ['user'=>1,'developer'=>2,'founder'=>3][$r] ?? 0; }
function require_role(string ...$roles): array { $u=require_auth(); if(!in_array($u['role'],$roles,true)) { if(is_api_request()) json_response(['ok'=>false,'error'=>'Forbidden'],403); http_response_code(403); render_page('403',['title'=>'Нет доступа']); exit; } return $u; }
function setting(string $key, mixed $default=null): mixed { static $cache=[]; if(array_key_exists($key,$cache)) return $cache[$key]; try { $st=db()->prepare('SELECT value FROM system_settings WHERE `key`=?'); $st->execute([$key]); $r=$st->fetchColumn(); return $cache[$key]=$r===false?$default:$r; } catch(Throwable) { return $default; } }
function set_setting(string $key,string $value,int $uid): void { $st=db()->prepare('INSERT INTO system_settings (`key`,`value`,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP'); $st->execute([$key,$value,$uid]); }
function audit(int $listId, ?int $taskId, int $actor, string $action, ?int $from=null, ?int $to=null, ?array $before=null, ?array $after=null, ?array $meta=null): void { $st=db()->prepare('INSERT INTO todo_audit_logs(todo_list_id,task_id,actor_user_id,action,from_category_id,to_category_id,before_data,after_data,metadata) VALUES (?,?,?,?,?,?,?,?,?)'); $st->execute([$listId,$taskId,$actor,$action,$from,$to,$before?json_encode($before,JSON_UNESCAPED_UNICODE):null,$after?json_encode($after,JSON_UNESCAPED_UNICODE):null,$meta?json_encode($meta,JSON_UNESCAPED_UNICODE):null]); }
function event_log(string $type,string $title,int $actor,?int $listId=null,?int $taskId=null,?array $meta=null): void { $st=db()->prepare('INSERT INTO events(type,title,actor_user_id,todo_list_id,task_id,metadata) VALUES (?,?,?,?,?,?)'); $st->execute([$type,$title,$actor,$listId,$taskId,$meta?json_encode($meta,JSON_UNESCAPED_UNICODE):null]); }
function list_access(int $listId, ?array $u, bool $write=false): bool {
    $st=db()->prepare('SELECT visibility,is_archived FROM todo_lists WHERE id=?'); $st->execute([$listId]); $list=$st->fetch(); if(!$list) return false;
    // Founder is the unrestricted administrative role and can work with archived lists too.
    if($u && $u['role']==='founder') return true;
    if((int)$list['is_archived']===1) return false;
    $v=(string)$list['visibility'];
    // PUBLIC_READ is capability-link access only. Internal numeric routes never grant it implicitly.
    if(!$u) return false;
    if($u['role']==='developer') return true;
    if($write) return false;
    if($v==='SELECTED_USERS') { $q=db()->prepare('SELECT 1 FROM todo_list_viewers WHERE todo_list_id=? AND user_id=?'); $q->execute([$listId,$u['id']]); return (bool)$q->fetchColumn(); }
    return false;
}

function recent_visible_events(array $u, int $limit=5): array {
    $limit=max(1,min(50,$limit));
    if($u['role']==='founder') {
        return db()->query("SELECT e.*,actor.login FROM events e LEFT JOIN users actor ON actor.id=e.actor_user_id ORDER BY e.id DESC LIMIT $limit")->fetchAll();
    }

    if($u['role']==='developer') {
        $sql="SELECT e.*,actor.login FROM events e LEFT JOIN users actor ON actor.id=e.actor_user_id LEFT JOIN todo_lists l ON l.id=e.todo_list_id WHERE (e.todo_list_id IS NOT NULL AND l.is_archived=0) OR (e.todo_list_id IS NULL AND (e.actor_user_id=? OR CAST(JSON_UNQUOTE(JSON_EXTRACT(e.metadata,'$.user_id')) AS UNSIGNED)=?)) ORDER BY e.id DESC LIMIT $limit";
        $st=db()->prepare($sql);$st->execute([(int)$u['id'],(int)$u['id']]);return $st->fetchAll();
    }

    $sql="SELECT DISTINCT e.*,actor.login
          FROM events e
          LEFT JOIN users actor ON actor.id=e.actor_user_id
          LEFT JOIN todo_lists l ON l.id=e.todo_list_id
          LEFT JOIN todo_list_viewers v ON v.todo_list_id=l.id AND v.user_id=?
          WHERE (e.todo_list_id IS NULL AND (e.actor_user_id=? OR CAST(JSON_UNQUOTE(JSON_EXTRACT(e.metadata,'$.user_id')) AS UNSIGNED)=?))
             OR (e.todo_list_id IS NOT NULL AND l.is_archived=0 AND l.visibility='SELECTED_USERS' AND v.user_id IS NOT NULL)
          ORDER BY e.id DESC LIMIT $limit";
    $st=db()->prepare($sql);$st->execute([(int)$u['id'],(int)$u['id'],(int)$u['id']]);return $st->fetchAll();
}

function visibility_label(string $visibility): string {
    return match($visibility) {
        'TEAM_ONLY' => 'Только команда',
        'SELECTED_USERS' => 'Команда + выбранные пользователи',
        'PUBLIC_READ' => 'Публичный просмотр',
        default => $visibility,
    };
}
function page_url(string $path, int $page, array $query=[]): string {
    $query=array_filter($query, static fn($v)=>$v!=='' && $v!==null);
    if($page>1) $query['page']=$page; else unset($query['page']);
    return url($path).($query?'?'.http_build_query($query):'');
}
function touch_user_activity(int $userId): void {
    $now=time();
    if((int)($_SESSION['activity_touch']??0)>$now-30) return;
    try { db()->prepare('UPDATE users SET last_seen_at=NOW() WHERE id=?')->execute([$userId]); $_SESSION['activity_touch']=$now; } catch(Throwable) {}
}
function ensure_runtime_schema(): void {
    static $done=false; if($done) return; $done=true;
    try {
        $db=(string)config('database.database','');
        $st=db()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='users' AND COLUMN_NAME='last_seen_at'");
        $st->execute([$db]);
        if(!(int)$st->fetchColumn()) db()->exec('ALTER TABLE users ADD COLUMN last_seen_at TIMESTAMP NULL DEFAULT NULL AFTER last_login_at');
        db()->exec("CREATE TABLE IF NOT EXISTS auth_rate_limits (key_hash CHAR(64) PRIMARY KEY, attempts INT UNSIGNED NOT NULL DEFAULT 0, window_started_at DATETIME NOT NULL, last_attempt_at DATETIME NOT NULL, INDEX(last_attempt_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        db()->exec("CREATE TABLE IF NOT EXISTS user_passkeys (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,name VARCHAR(80) NOT NULL,credential_id BLOB NOT NULL,credential_id_hash CHAR(64) NOT NULL UNIQUE,public_key TEXT NOT NULL,sign_count BIGINT UNSIGNED NOT NULL DEFAULT 0,transports VARCHAR(255) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,last_used_at TIMESTAMP NULL,INDEX(user_id),CONSTRAINT fk_passkey_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch(Throwable) {}
}

function icon(string $name): string {
    $p=['home'=>'M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z','tasks'=>'M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01','user'=>'M20 21a8 8 0 0 0-16 0m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8','shield'=>'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10','log'=>'M3 3h18v18H3zM7 8h10M7 12h10M7 16h6','plus'=>'M12 5v14M5 12h14','settings'=>'M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.12 2.12-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V20.3h-3v-.08a1.7 1.7 0 0 0-1.03-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-2.12-2.12.06-.06A1.7 1.7 0 0 0 7 15a1.7 1.7 0 0 0-1.56-1.03H5.3v-3h.14A1.7 1.7 0 0 0 7 9.94a1.7 1.7 0 0 0-.34-1.88L6.6 8l2.12-2.12.06.06a1.7 1.7 0 0 0 1.88.34A1.7 1.7 0 0 0 11.7 4.7v-.08h3v.08a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06L19.8 8l-.06.06a1.7 1.7 0 0 0-.34 1.88 1.7 1.7 0 0 0 1.56 1.03h.14v3h-.14A1.7 1.7 0 0 0 19.4 15','logout'=>'M10 17l5-5-5-5M15 12H3M21 3v18h-8'];
    $d=$p[$name]??$p['tasks']; return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="'.e($d).'"/></svg>';
}
function render_page(string $view,array $vars=[]): void { extract($vars); $user=current_user(); $contentView=__DIR__.'/views/'.$view.'.php'; require __DIR__.'/views/layout.php'; }

function render_standalone_error(int $code,string $title,string $message): never {
    $home=url('/'); $css=url('/assets/app.css'); http_response_code($code);
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e((string)$code).' · '.e($title).' · '.e(app_name()).'</title><link rel="stylesheet" href="'.e($css).'"></head><body class="error-body"><div class="ambient a1"></div><div class="ambient a2"></div><main class="error-shell"><section class="error-card card"><div class="error-icon">!</div><h1>'.e($title).'</h1><p class="muted">'.e($message).'</p><div class="error-actions"><a class="btn primary" href="'.e($home).'">На главную</a><button class="btn ghost" id="error-back" type="button">Назад</button></div></section></main><script nonce="'.e(csp_nonce()).'">document.getElementById("error-back")?.addEventListener("click",()=>history.back());</script></body></html>'; exit;
}
