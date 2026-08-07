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
  if($method==='POST'){ verify_csrf($_POST['_csrf']??null); $login=trim($_POST['login']??''); $pass=$_POST['password']??''; $st=db()->prepare('SELECT * FROM users WHERE login=? LIMIT 1'); $st->execute([$login]); $u=$st->fetch(); if(!$u || !$u['is_active'] || !password_verify($pass,$u['password_hash'])) $error='Неверный логин или пароль.'; else { session_regenerate_id(true); $_SESSION['uid']=$u['id']; db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]); redirect('/dashboard'); }}
  render_page('login',['title'=>'Вход','error'=>$error]); exit;
}
if($path==='/register'){
  if(current_user()) redirect('/dashboard'); $error=null;
  if($method==='POST'){ verify_csrf($_POST['_csrf']??null); if(setting('registration_enabled','1')!=='1') $error='Регистрация временно закрыта.'; else { $login=trim($_POST['login']??''); $pass=$_POST['password']??''; $pc=$_POST['password_confirmation']??''; if(!preg_match('/^[A-Za-z0-9_-]{3,32}$/',$login))$error='Логин: 3–32 символа, латиница, цифры, _ и -.'; elseif(strlen($pass)<8)$error='Пароль должен быть не короче 8 символов.'; elseif($pass!==$pc)$error='Пароли не совпадают.'; else { try{$st=db()->prepare("INSERT INTO users(login,password_hash,role) VALUES (?,?,'user')");$st->execute([$login,password_hash($pass,PASSWORD_ARGON2ID)]);$id=(int)db()->lastInsertId();event_log('USER_REGISTERED',"Зарегистрирован пользователь $login",$id);redirect('/login');}catch(PDOException $e){$error='Этот логин уже занят.';} } }}
  render_page('register',['title'=>'Регистрация','error'=>$error]); exit;
}
if($path==='/logout' && $method==='POST'){ verify_csrf($_POST['_csrf']??null); $_SESSION=[]; session_destroy(); redirect('/login'); }
if($path==='/dashboard'){
  $u=require_auth(); $events=recent_visible_events($u,5);
  if($u['role']==='user'){ $st=db()->prepare("SELECT COUNT(DISTINCT l.id) FROM todo_lists l LEFT JOIN todo_list_viewers v ON v.todo_list_id=l.id AND v.user_id=? WHERE l.is_archived=0 AND (l.visibility='PUBLIC_READ' OR (l.visibility='SELECTED_USERS' AND v.user_id IS NOT NULL))");$st->execute([$u['id']]);$boardCount=(int)$st->fetchColumn(); } else $boardCount=(int)db()->query('SELECT COUNT(*) FROM todo_lists WHERE is_archived=0')->fetchColumn();
  render_page('dashboard',compact('events','boardCount')+['title'=>'Главная']);exit;
}
if($path==='/profile'){
  $u=require_auth(); if($method==='POST'){verify_csrf($_POST['_csrf']??null);if(($_POST['action']??'')==='password' && strlen($_POST['password']??'')>=8){db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($_POST['password'],PASSWORD_ARGON2ID),$u['id']]);redirect('/profile');}}
  render_page('profile',['title'=>'Профиль']);exit;
}
if($path==='/todos'){
  $u=require_auth();
  if($method==='POST'){if($u['role']!=='founder') {http_response_code(403);exit;}verify_csrf($_POST['_csrf']??null);$title=trim($_POST['title']??'');if($title!==''){db()->prepare('INSERT INTO todo_lists(title,description,created_by) VALUES (?,?,?)')->execute([$title,trim($_POST['description']??''),$u['id']]);$id=(int)db()->lastInsertId();foreach(['Backlog','To Do','In Progress','Done'] as $i=>$c)db()->prepare('INSERT INTO todo_categories(todo_list_id,title,position) VALUES (?,?,?)')->execute([$id,$c,$i]);audit($id,null,$u['id'],'TODO_LIST_CREATED');event_log('TODO_LIST_CREATED',"Создан To-do список «$title»",$u['id'],$id);redirect('/todos/'.$id);}}
  if($u['role']==='user'){$st=db()->prepare("SELECT DISTINCT l.* FROM todo_lists l LEFT JOIN todo_list_viewers v ON v.todo_list_id=l.id AND v.user_id=? WHERE l.is_archived=0 AND (l.visibility='PUBLIC_READ' OR (l.visibility='SELECTED_USERS' AND v.user_id IS NOT NULL)) ORDER BY l.position,l.id");$st->execute([$u['id']]);$lists=$st->fetchAll();}else{$lists=db()->query('SELECT * FROM todo_lists WHERE is_archived=0 ORDER BY position,id')->fetchAll();}
  render_page('todos',compact('lists')+['title'=>'To-do']);exit;
}
if(preg_match('#^/todos/(\d+)$#',$path,$m)){
  $u=require_auth();$id=(int)$m[1]; if(!list_access($id,$u,false)){http_response_code(403);render_page('403',['title'=>'Нет доступа']);exit;}
  $st=db()->prepare('SELECT * FROM todo_lists WHERE id=? AND is_archived=0');$st->execute([$id]);$list=$st->fetch(); if(!$list){http_response_code(404);render_page('404',['title'=>'Не найдено']);exit;}
  $st=db()->prepare('SELECT * FROM todo_categories WHERE todo_list_id=? ORDER BY position,id');$st->execute([$id]);$categories=$st->fetchAll();$tags=db()->query('SELECT * FROM tags ORDER BY name')->fetchAll();$normalUsers=[];$viewerIds=[];if($u['role']==='founder'){$normalUsers=db()->query("SELECT id,login FROM users WHERE role='user' AND is_active=1 ORDER BY login")->fetchAll();$vs=db()->prepare('SELECT user_id FROM todo_list_viewers WHERE todo_list_id=?');$vs->execute([$id]);$viewerIds=array_map('intval',$vs->fetchAll(PDO::FETCH_COLUMN));}
  render_page('board',compact('list','categories','tags','normalUsers','viewerIds')+['title'=>$list['title']]);exit;
}
if(preg_match('#^/public/todos/([A-Za-z0-9_-]{20,64})$#',$path,$m)){
  $st=db()->prepare("SELECT * FROM todo_lists WHERE public_slug=? AND visibility='PUBLIC_READ' AND is_archived=0");$st->execute([$m[1]]);$list=$st->fetch();if(!$list){http_response_code(404);render_page('404',['title'=>'Не найдено']);exit;}require dirname(__DIR__).'/app/views/public_board.php';exit;
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
      db()->prepare('UPDATE users SET role=?,is_active=? WHERE id=?')->execute([$role,(int)($_POST['is_active']??1),$targetId]);
      event_log('USER_ROLE_CHANGED','Изменены права пользователя',$u['id'],null,null,['user_id'=>$targetId]);
      $_SESSION['flash_success']='Пользователь обновлён.';
    } elseif($a==='user_create') {
      $login=trim((string)($_POST['login']??''));$pass=(string)($_POST['password']??'');$role=(string)($_POST['role']??'user');
      if(!preg_match('/^[A-Za-z0-9_-]{3,32}$/',$login)) $_SESSION['flash_error']='Логин: 3–32 символа, латиница, цифры, _ и -.';
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
    }
    redirect('/admin/registration');
  }
  render_page('admin_registration',['title'=>'Регистрация']);exit;
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
