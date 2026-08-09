<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
ensure_runtime_schema();
$path=request_path();
$method=$_SERVER['REQUEST_METHOD']??'GET';

try {
if(str_starts_with($path,'/api/')) require __DIR__.'/api/router.php';

if($path==='/' ) { current_user()?redirect('/dashboard'):redirect('/login'); }
if($path==='/login'){
  if(current_user()) redirect('/dashboard'); $error=null;
  if($method==='POST'){ verify_csrf($_POST['_csrf']??null);if(setting('password_login_enabled','1')!=='1'){http_response_code(403);$error='Вход по паролю отключён. Используйте Passkey.';}else{$login=trim((string)($_POST['login']??'')); $pass=(string)($_POST['password']??''); $retry=max(rate_limit_consume('login-account',$login,5,900),rate_limit_consume('login-ip','',25,900)); if($retry>0){http_response_code(429);$error='Слишком много попыток входа. Повторите позже.';} else { $st=db()->prepare('SELECT * FROM users WHERE login=? LIMIT 1'); $st->execute([$login]); $u=$st->fetch(); $verified=password_verify($pass,$u['password_hash']??'$argon2id$v=19$m=65536,t=4,p=1$ZjJpZVNaN203SDJZYXBtQQ$QVEpcweolqbfykY7iBjVPz0JAv07lmBP99LmbCGwewQ'); if(!$u || !$u['is_active'] || !$verified) { $error='Неверный логин или пароль.'; } else { rate_limit_clear('login-account',$login);if(password_needs_rehash((string)$u['password_hash'],PASSWORD_ARGON2ID))db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($pass,PASSWORD_ARGON2ID),$u['id']]);session_regenerate_id(true);rotate_csrf_token();$_SESSION['uid']=$u['id'];$_SESSION['auth_version']=$u['auth_version'];$_SESSION['reauthenticated_at']=time();db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]);redirect('/dashboard'); } }}}
  render_page('login',['title'=>'Вход','error'=>$error]); exit;
}
if($path==='/register'){
  if(current_user()) redirect('/dashboard'); $error=null;
  if($method==='POST'){ verify_csrf($_POST['_csrf']??null); $retry=rate_limit_consume('register-ip','',5,3600); if($retry>0){http_response_code(429);$error='Слишком много попыток регистрации. Повторите позже.';} elseif(setting('registration_enabled','1')!=='1') $error='Регистрация временно закрыта.'; else { $login=trim((string)($_POST['login']??'')); $pass=(string)($_POST['password']??''); $pc=(string)($_POST['password_confirmation']??''); if(!preg_match('/^[A-Za-z0-9_-]{3,32}$/',$login))$error='Логин: 3–32 символа, латиница, цифры, _ и -.'; elseif(strlen($pass)<8)$error='Пароль должен быть не короче 8 символов.'; elseif($pass!==$pc)$error='Пароли не совпадают.'; else { try{$st=db()->prepare("INSERT INTO users(login,password_hash,role) VALUES (?,?,'user')");$st->execute([$login,password_hash($pass,PASSWORD_ARGON2ID)]);$id=(int)db()->lastInsertId();event_log('USER_REGISTERED',"Зарегистрирован пользователь $login",$id);if(setting('password_login_enabled','1')!=='1'){session_regenerate_id(true);rotate_csrf_token();$_SESSION['uid']=$id;$_SESSION['auth_version']=1;$_SESSION['reauthenticated_at']=time();$_SESSION['passkey_setup_required']=1;redirect('/profile');}redirect('/login');}catch(PDOException $e){$error='Этот логин уже занят.';} } }}
  render_page('register',['title'=>'Регистрация','error'=>$error]); exit;
}
if($path==='/logout' && $method==='POST'){ verify_csrf($_POST['_csrf']??null); destroy_session(); redirect('/login'); }
if($path==='/dashboard'){
  $u=require_auth(); $events=recent_visible_events($u,5);
  if($u['role']==='user'){ $st=db()->prepare("SELECT COUNT(DISTINCT l.id) FROM todo_lists l LEFT JOIN todo_list_viewers v ON v.todo_list_id=l.id AND v.user_id=? WHERE l.is_archived=0 AND l.visibility='SELECTED_USERS' AND v.user_id IS NOT NULL");$st->execute([$u['id']]);$boardCount=(int)$st->fetchColumn(); } else $boardCount=(int)db()->query('SELECT COUNT(*) FROM todo_lists WHERE is_archived=0')->fetchColumn();
  render_page('dashboard',compact('events','boardCount')+['title'=>'Главная']);exit;
}
if($path==='/profile'){
  $u=require_auth();$profileError=null;$profileSuccess=null;if($method==='POST'){verify_csrf($_POST['_csrf']??null);if(($_POST['action']??'')==='password'){ $subject=(string)$u['id'];$retry=rate_limit_consume('password-change',$subject,5,900);$current=(string)($_POST['current_password']??'');$newPassword=(string)($_POST['password']??'');$st=db()->prepare('SELECT password_hash FROM users WHERE id=?');$st->execute([$u['id']]);$hash=(string)$st->fetchColumn();if($retry>0){http_response_code(429);$profileError='Слишком много попыток. Повторите позже.';}elseif(!password_verify($current,$hash)){$profileError='Текущий пароль указан неверно.';}elseif(strlen($newPassword)<8)$profileError='Новый пароль должен быть не короче 8 символов.';else{rate_limit_clear('password-change',$subject);db()->prepare('UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE id=?')->execute([password_hash($newPassword,PASSWORD_ARGON2ID),$u['id']]);$st=db()->prepare('SELECT auth_version FROM users WHERE id=?');$st->execute([$u['id']]);$_SESSION['auth_version']=(int)$st->fetchColumn();$_SESSION['reauthenticated_at']=time();session_regenerate_id(true);rotate_csrf_token();$profileSuccess='Пароль обновлён.';}}}
  $st=db()->prepare('SELECT id,name,created_at,last_used_at FROM user_passkeys WHERE user_id=? ORDER BY id DESC');$st->execute([$u['id']]);$passkeys=$st->fetchAll();render_page('profile',['title'=>'Профиль','profileError'=>$profileError,'profileSuccess'=>$profileSuccess,'passkeys'=>$passkeys]);exit;
}
if($path==='/todos'){
  $u=require_auth();
  if($method==='POST'){if($u['role']!=='founder') {http_response_code(403);exit;}verify_csrf($_POST['_csrf']??null);$title=trim((string)($_POST['title']??''));$description=trim((string)($_POST['description']??''));if(mb_strlen($title)>160||mb_strlen($description)>20000){http_response_code(422);render_page('todos',['lists'=>[],'title'=>'To-do']);exit;}if($title!==''){db()->prepare('INSERT INTO todo_lists(title,description,created_by) VALUES (?,?,?)')->execute([$title,$description,$u['id']]);$id=(int)db()->lastInsertId();foreach(['Backlog','To Do','In Progress','Done'] as $i=>$c)db()->prepare('INSERT INTO todo_categories(todo_list_id,title,position) VALUES (?,?,?)')->execute([$id,$c,$i]);audit($id,null,$u['id'],'TODO_LIST_CREATED');event_log('TODO_LIST_CREATED',"Создан To-do список «$title»",$u['id'],$id);redirect('/todos/'.$id);}}
  if($u['role']==='user'){$st=db()->prepare("SELECT DISTINCT l.* FROM todo_lists l LEFT JOIN todo_list_viewers v ON v.todo_list_id=l.id AND v.user_id=? WHERE l.is_archived=0 AND l.visibility='SELECTED_USERS' AND v.user_id IS NOT NULL ORDER BY l.position,l.id");$st->execute([$u['id']]);$lists=$st->fetchAll();}else{$lists=db()->query('SELECT * FROM todo_lists WHERE is_archived=0 ORDER BY position,id')->fetchAll();}
  render_page('todos',compact('lists')+['title'=>'To-do']);exit;
}
if(preg_match('#^/todos/(\d+)$#',$path,$m)){
  $u=require_auth();$id=(int)$m[1]; if(!list_access($id,$u,false)){http_response_code(403);render_page('403',['title'=>'Нет доступа']);exit;}
  $st=db()->prepare('SELECT * FROM todo_lists WHERE id=? AND is_archived=0');$st->execute([$id]);$list=$st->fetch(); if(!$list){http_response_code(404);render_page('404',['title'=>'Не найдено']);exit;}
  $st=db()->prepare('SELECT * FROM todo_categories WHERE todo_list_id=? ORDER BY position,id');$st->execute([$id]);$categories=$st->fetchAll();$tags=db()->query('SELECT * FROM tags ORDER BY name')->fetchAll();$normalUsers=[];$viewerIds=[];if($u['role']==='founder'){$normalUsers=db()->query("SELECT id,login FROM users WHERE role='user' AND is_active=1 ORDER BY login")->fetchAll();$vs=db()->prepare('SELECT user_id FROM todo_list_viewers WHERE todo_list_id=?');$vs->execute([$id]);$viewerIds=array_map('intval',$vs->fetchAll(PDO::FETCH_COLUMN));}
  render_page('board',compact('list','categories','tags','normalUsers','viewerIds')+['title'=>$list['title']]);exit;
}
if(preg_match('#^/public/todos/([A-Za-z0-9_-]{20,64})$#',$path,$m)){
  no_store_headers();$st=db()->prepare("SELECT * FROM todo_lists WHERE public_slug=? AND visibility='PUBLIC_READ' AND is_archived=0");$st->execute([$m[1]]);$list=$st->fetch();if(!$list){http_response_code(404);render_page('404',['title'=>'Не найдено']);exit;}require dirname(__DIR__).'/app/views/public_board.php';exit;
}
if($path==='/admin'){
  require_role('founder');
  $stats=[];
  $stats['users']=(int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
  $stats['developers']=(int)db()->query("SELECT COUNT(*) FROM users WHERE role='developer' AND is_active=1")->fetchColumn();
  $stats['regular_users']=(int)db()->query("SELECT COUNT(*) FROM users WHERE role='user' AND is_active=1")->fetchColumn();
  $stats['tasks']=(int)db()->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
  $stats['lists']=(int)db()->query('SELECT COUNT(*) FROM todo_lists WHERE is_archived=0')->fetchColumn();
  $stats['actions_today']=(int)db()->query('SELECT COUNT(*) FROM todo_audit_logs WHERE created_at>=CURRENT_DATE()')->fetchColumn();
  try{$onlineUsers=db()->query("SELECT login,role,last_seen_at FROM users WHERE is_active=1 AND last_seen_at>=NOW()-INTERVAL 5 MINUTE ORDER BY last_seen_at DESC LIMIT 20")->fetchAll();}catch(Throwable){$onlineUsers=db()->query("SELECT login,role,last_login_at last_seen_at FROM users WHERE is_active=1 AND last_login_at>=NOW()-INTERVAL 5 MINUTE ORDER BY last_login_at DESC LIMIT 20")->fetchAll();}$stats['online']=count($onlineUsers);
  render_page('admin',compact('stats','onlineUsers')+['title'=>'Админ-панель']);exit;
}
if($path==='/admin/users'){
  $u=require_role('founder');
  if($method==='POST'){
    verify_csrf($_POST['_csrf']??null);$a=$_POST['action']??'';
    if($a==='user_update'){
      $targetId=(int)($_POST['id']??0);
      if($targetId===(int)$u['id']){ $_SESSION['flash_error']='Нельзя изменять собственную роль или статус.'; redirect('/admin/users'); }
      $role=(string)($_POST['role']??'user');if(!in_array($role,['user','developer','founder'],true))$role='user';
      $active=(int)($_POST['is_active']??1);if($active&&setting('password_login_enabled','1')!=='1'){$st=db()->prepare('SELECT 1 FROM user_passkeys WHERE user_id=? LIMIT 1');$st->execute([$targetId]);if(!$st->fetchColumn()){$_SESSION['flash_error']='Нельзя активировать пользователя без Passkey, пока вход по паролю отключён.';redirect('/admin/users');}}
      db()->prepare('UPDATE users SET role=?,is_active=?,auth_version=auth_version+1 WHERE id=?')->execute([$role,$active,$targetId]);
      event_log('USER_ROLE_CHANGED','Изменены права пользователя',$u['id'],null,null,['user_id'=>$targetId]);
      $_SESSION['flash_success']='Пользователь обновлён.';
    } elseif($a==='user_create') {
      $login=trim((string)($_POST['login']??''));$pass=(string)($_POST['password']??'');$role=(string)($_POST['role']??'user');
      if(setting('password_login_enabled','1')!=='1') $_SESSION['flash_error']='При отключённом входе по паролю создавайте аккаунт через регистрацию, чтобы сразу добавить Passkey.';
      elseif(!preg_match('/^[A-Za-z0-9_-]{3,32}$/',$login)) $_SESSION['flash_error']='Логин: 3–32 символа, латиница, цифры, _ и -.';
      elseif(strlen($pass)<8) $_SESSION['flash_error']='Пароль должен быть не короче 8 символов.';
      elseif(!in_array($role,['user','developer','founder'],true)) $_SESSION['flash_error']='Некорректная роль.';
      else { try { db()->prepare('INSERT INTO users(login,password_hash,role,is_active) VALUES (?,?,?,1)')->execute([$login,password_hash($pass,PASSWORD_ARGON2ID),$role]); $newId=(int)db()->lastInsertId(); event_log('USER_CREATED','Администратор создал пользователя '.$login,$u['id'],null,null,['user_id'=>$newId,'role'=>$role]); $_SESSION['flash_success']='Пользователь создан.'; } catch(PDOException) { $_SESSION['flash_error']='Пользователь с таким логином уже существует.'; } }
    }
    redirect('/admin/users');
  }
  $perPage=25;$totalUsers=(int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();$totalPages=max(1,(int)ceil($totalUsers/$perPage));$page=max(1,min($totalPages,(int)($_GET['page']??1)));$offset=($page-1)*$perPage;
  try{$users=db()->query("SELECT id,login,role,is_active,created_at,last_seen_at FROM users ORDER BY id DESC LIMIT $perPage OFFSET $offset")->fetchAll();}catch(Throwable){$users=db()->query("SELECT id,login,role,is_active,created_at,last_login_at last_seen_at FROM users ORDER BY id DESC LIMIT $perPage OFFSET $offset")->fetchAll();}
  $flashError=$_SESSION['flash_error']??null;$flashSuccess=$_SESSION['flash_success']??null;unset($_SESSION['flash_error'],$_SESSION['flash_success']);render_page('admin_users',compact('users','page','totalPages','totalUsers','flashError','flashSuccess')+['title'=>'Пользователи']);exit;
}
if($path==='/admin/registration'){
  $u=require_role('founder');
  if($method==='POST'){
    verify_csrf($_POST['_csrf']??null);
    if(($_POST['action']??'')==='toggle_registration'){
      $new=setting('registration_enabled','1')==='1'?'0':'1';set_setting('registration_enabled',$new,$u['id']);
      event_log($new==='1'?'REGISTRATION_ENABLED':'REGISTRATION_DISABLED',$new==='1'?'Регистрация включена':'Регистрация отключена',$u['id']);
    } elseif(($_POST['action']??'')==='toggle_password_login'){
      $new=setting('password_login_enabled','1')==='1'?'0':'1';
      if($new==='0'){$missing=(int)db()->query('SELECT COUNT(*) FROM users u WHERE u.is_active=1 AND NOT EXISTS (SELECT 1 FROM user_passkeys p WHERE p.user_id=u.id)')->fetchColumn();if($missing>0){$_SESSION['flash_error']='Сначала добавьте Passkey всем активным пользователям.';redirect('/admin/registration');}}
      set_setting('password_login_enabled',$new,$u['id']);event_log($new==='1'?'PASSWORD_LOGIN_ENABLED':'PASSWORD_LOGIN_DISABLED',$new==='1'?'Вход по паролю включён':'Вход по паролю отключён',$u['id']);
    }
    redirect('/admin/registration');
  }
  $missingPasskeys=(int)db()->query('SELECT COUNT(*) FROM users u WHERE u.is_active=1 AND NOT EXISTS (SELECT 1 FROM user_passkeys p WHERE p.user_id=u.id)')->fetchColumn();$flashError=$_SESSION['flash_error']??null;unset($_SESSION['flash_error']);render_page('admin_registration',['title'=>'Регистрация','missingPasskeys'=>$missingPasskeys,'flashError'=>$flashError]);exit;
}
if($path==='/admin/archive'){
  $u=require_role('founder');
  if($method==='POST'){
    verify_csrf($_POST['_csrf']??null);
    if(($_POST['action']??'')==='restore'){
      $id=(int)($_POST['id']??0);
      $st=db()->prepare('SELECT * FROM todo_lists WHERE id=? AND is_archived=1');$st->execute([$id]);$list=$st->fetch();
      if($list){
        db()->prepare('UPDATE todo_lists SET is_archived=0,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);
        audit($id,null,$u['id'],'TODO_LIST_RESTORED',null,null,['is_archived'=>1],['is_archived'=>0]);
        event_log('TODO_LIST_RESTORED','Восстановлен To-do список «'.$list['title'].'»',$u['id'],$id);
        $_SESSION['flash_success']='To-do список восстановлен.';
      } else $_SESSION['flash_error']='Архивный To-do список не найден.';
    }
    redirect('/admin/archive');
  }
  $perPage=25;$totalArchived=(int)db()->query('SELECT COUNT(*) FROM todo_lists WHERE is_archived=1')->fetchColumn();$totalPages=max(1,(int)ceil($totalArchived/$perPage));$page=max(1,min($totalPages,(int)($_GET['page']??1)));$offset=($page-1)*$perPage;
  $archivedLists=db()->query("SELECT l.*,u.login creator,(SELECT COUNT(*) FROM tasks t WHERE t.todo_list_id=l.id) task_count,(SELECT COUNT(*) FROM todo_categories c WHERE c.todo_list_id=l.id) category_count FROM todo_lists l JOIN users u ON u.id=l.created_by WHERE l.is_archived=1 ORDER BY l.updated_at DESC,l.id DESC LIMIT $perPage OFFSET $offset")->fetchAll();
  $flashError=$_SESSION['flash_error']??null;$flashSuccess=$_SESSION['flash_success']??null;unset($_SESSION['flash_error'],$_SESSION['flash_success']);
  render_page('admin_archive',compact('archivedLists','page','totalPages','totalArchived','flashError','flashSuccess')+['title'=>'Архив To-do']);exit;
}
if(preg_match('#^/admin/archive/(\d+)$#',$path,$m)){
  require_role('founder');$id=(int)$m[1];
  $st=db()->prepare('SELECT l.*,u.login creator FROM todo_lists l JOIN users u ON u.id=l.created_by WHERE l.id=? AND l.is_archived=1');$st->execute([$id]);$list=$st->fetch();
  if(!$list){http_response_code(404);render_page('404',['title'=>'Не найдено']);exit;}
  $st=db()->prepare('SELECT * FROM todo_categories WHERE todo_list_id=? ORDER BY position,id');$st->execute([$id]);$categories=$st->fetchAll();
  $tags=db()->query('SELECT * FROM tags ORDER BY name')->fetchAll();
  render_page('admin_archive_view',compact('list','categories','tags')+['title'=>'Архив: '.$list['title']]);exit;
}
if($path==='/admin/logs'){
  require_role('founder');$where=[];$params=[];
  if(!empty($_GET['list'])){$where[]='a.todo_list_id=?';$params[]=(int)$_GET['list'];}
  if(!empty($_GET['actor'])){$where[]='a.actor_user_id=?';$params[]=(int)$_GET['actor'];}
  if(!empty($_GET['action'])){$where[]='a.action LIKE ?';$params[]='%'.trim((string)$_GET['action']).'%';}
  $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
  $count=db()->prepare('SELECT COUNT(*) FROM todo_audit_logs a'.$whereSql);$count->execute($params);$totalLogs=(int)$count->fetchColumn();$perPage=50;$totalPages=max(1,(int)ceil($totalLogs/$perPage));$page=max(1,min($totalPages,(int)($_GET['page']??1)));$offset=($page-1)*$perPage;
  $sql="SELECT a.*,u.login,l.title list_title,t.title task_title,fc.title from_title,tc.title to_title FROM todo_audit_logs a JOIN users u ON u.id=a.actor_user_id JOIN todo_lists l ON l.id=a.todo_list_id LEFT JOIN tasks t ON t.id=a.task_id LEFT JOIN todo_categories fc ON fc.id=a.from_category_id LEFT JOIN todo_categories tc ON tc.id=a.to_category_id".$whereSql." ORDER BY a.id DESC LIMIT $perPage OFFSET $offset";
  $st=db()->prepare($sql);$st->execute($params);$logs=$st->fetchAll();$lists=db()->query('SELECT id,title FROM todo_lists ORDER BY title')->fetchAll();$actors=db()->query('SELECT id,login FROM users ORDER BY login')->fetchAll();
  render_page('logs',compact('logs','lists','actors','page','totalPages','totalLogs')+['title'=>'Журнал To-do']);exit;
}
http_response_code(404);render_page('404',['title'=>'Не найдено']);
} catch(Throwable $e){ http_response_code(500); if(config_bool('app.debug')){echo '<pre>'.e((string)$e).'</pre>';} else { render_standalone_error(500,'Внутренняя ошибка','Произошла ошибка на сервере. Попробуйте повторить запрос позже.'); } }
