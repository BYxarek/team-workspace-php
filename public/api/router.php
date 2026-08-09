<?php
declare(strict_types=1);
$u=current_user();
if($method!=='GET') verify_csrf();
$data=in_array($method,['POST','PUT','PATCH'],true)?body_json():[];

if($path==='/api/passkeys/login/options' && $method==='POST'){
 $login=trim((string)($data['login']??''));$retry=max(rate_limit_retry_after('passkey-login-account',$login,10,900),rate_limit_retry_after('passkey-login-ip','',40,900));if($retry>0)json_response(['ok'=>false,'error'=>'Слишком много попыток. Повторите позже.'],429);
 $st=db()->prepare('SELECT id FROM users WHERE login=? AND is_active=1');$st->execute([$login]);$uid=(int)$st->fetchColumn();if(!$uid)json_response(['ok'=>false,'error'=>'Для этого аккаунта Passkey не найден.'],422);
 $st=db()->prepare('SELECT credential_id,transports FROM user_passkeys WHERE user_id=? ORDER BY id');$st->execute([$uid]);$credentials=$st->fetchAll();if(!$credentials)json_response(['ok'=>false,'error'=>'Для этого аккаунта Passkey не найден.'],422);
 $server=passkey_server();$args=$server->getGetArgs(array_column($credentials,'credential_id'),300,true,true,true,true,true,'required');passkey_store_challenge('login',$uid,$server->getChallenge());json_response(['ok'=>true,'publicKey'=>$args->publicKey]);
}
if($path==='/api/passkeys/login' && $method==='POST'){
 try{
  $response=is_array($data['response']??null)?$data['response']:[];
  $uid=passkey_challenge_user('login');$rawId=passkey_b64_decode((string)($data['rawId']??''));if(strlen($rawId)<1||strlen($rawId)>1024)throw new InvalidArgumentException('Некорректный идентификатор Passkey.');$st=db()->prepare('SELECT p.*,u.is_active FROM user_passkeys p JOIN users u ON u.id=p.user_id WHERE p.user_id=? AND p.credential_id_hash=? LIMIT 1');$st->execute([$uid,hash('sha256',$rawId)]);$key=$st->fetch();
  if(!$key||!$key['is_active']||!hash_equals($key['credential_id'],$rawId))throw new InvalidArgumentException('Passkey не найден.');
  $clientData=passkey_client_data((string)($response['clientDataJSON']??''));$server=passkey_server();$server->processGet($clientData,passkey_b64_decode((string)($response['authenticatorData']??'')),passkey_b64_decode((string)($response['signature']??'')),(string)$key['public_key'],passkey_take_challenge('login'),(int)$key['sign_count'],true,true);$counter=$server->getSignatureCounter()??(int)$key['sign_count'];
  db()->prepare('UPDATE user_passkeys SET sign_count=?,last_used_at=NOW() WHERE id=?')->execute([$counter,$key['id']]);rate_limit_clear('passkey-login-account',(string)$uid);
  session_regenerate_id(true);rotate_csrf_token();$_SESSION['uid']=$uid;unset($_SESSION['passkey_setup_required']);db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$uid]);json_response(['ok'=>true,'redirect'=>url('/dashboard')]);
 }catch(InvalidArgumentException|\lbuchs\WebAuthn\WebAuthnException $e){rate_limit_hit('passkey-login-ip','',900);json_response(['ok'=>false,'error'=>'Проверка Passkey не пройдена. Повторите попытку.'],422);}
}
if($path==='/api/passkeys/register/options' && $method==='POST'){
 $u=require_auth();$st=db()->prepare('SELECT credential_id FROM user_passkeys WHERE user_id=?');$st->execute([$u['id']]);$server=passkey_server();$args=$server->getCreateArgs(hash('sha256','user:'.$u['id'],true),$u['login'],$u['login'],300,'preferred','required',null,$st->fetchAll(PDO::FETCH_COLUMN));passkey_store_challenge('register',(int)$u['id'],$server->getChallenge());json_response(['ok'=>true,'publicKey'=>$args->publicKey]);
}
if($path==='/api/passkeys/register' && $method==='POST'){
 $u=require_auth();try{
  $response=is_array($data['response']??null)?$data['response']:[];
  if(passkey_challenge_user('register')!==(int)$u['id'])throw new InvalidArgumentException('Запрос Passkey недействителен.');$name=trim((string)($data['name']??''));if($name===''||mb_strlen($name)>80)throw new InvalidArgumentException('Укажите имя Passkey до 80 символов.');
  $clientData=passkey_client_data((string)($response['clientDataJSON']??''));$server=passkey_server();$registration=$server->processCreate($clientData,passkey_b64_decode((string)($response['attestationObject']??'')),passkey_take_challenge('register'),true,true,false);$rawId=passkey_b64_decode((string)($data['rawId']??''));if(strlen($rawId)<1||strlen($rawId)>1024||!hash_equals($registration->credentialId,$rawId))throw new InvalidArgumentException('Идентификатор Passkey не совпадает.');
  $transports=array_values(array_intersect(is_array($data['transports']??null)?$data['transports']:[],['usb','nfc','ble','internal','hybrid']));
  db()->prepare('INSERT INTO user_passkeys(user_id,name,credential_id,credential_id_hash,public_key,sign_count,transports) VALUES (?,?,?,?,?,?,?)')->execute([$u['id'],$name,$rawId,hash('sha256',$rawId),$registration->credentialPublicKey,$registration->signatureCounter??0,json_encode($transports)]);unset($_SESSION['passkey_setup_required']);
  json_response(['ok'=>true,'id'=>(int)db()->lastInsertId(),'name'=>$name],201);
 }catch(PDOException){json_response(['ok'=>false,'error'=>'Этот Passkey уже привязан.'],409);}catch(InvalidArgumentException|\lbuchs\WebAuthn\WebAuthnException $e){json_response(['ok'=>false,'error'=>'Не удалось проверить Passkey. Повторите попытку.'],422);}
}
if(preg_match('#^/api/passkeys/(\d+)$#',$path,$m) && $method==='DELETE'){
 $u=require_auth();$id=(int)$m[1];$st=db()->prepare('SELECT COUNT(*) FROM user_passkeys WHERE user_id=?');$st->execute([$u['id']]);if(setting('password_login_enabled','1')!=='1'&&(int)$st->fetchColumn()<=1)json_response(['ok'=>false,'error'=>'Нельзя удалить последний Passkey, пока вход по паролю отключён.'],409);
 $st=db()->prepare('DELETE FROM user_passkeys WHERE id=? AND user_id=?');$st->execute([$id,$u['id']]);if(!$st->rowCount())json_response(['ok'=>false,'error'=>'Passkey не найден.'],404);json_response(['ok'=>true]);
}

if($path==='/api/todo-lists' && $method==='GET'){
 $u=require_auth();
 if($u['role']==='user'){
   $st=db()->prepare("SELECT DISTINCT l.id,l.title,l.description,l.visibility,l.position,l.updated_at FROM todo_lists l LEFT JOIN todo_list_viewers v ON v.todo_list_id=l.id AND v.user_id=? WHERE l.is_archived=0 AND l.visibility='SELECTED_USERS' AND v.user_id IS NOT NULL ORDER BY l.position,l.id");
   $st->execute([$u['id']]);$lists=$st->fetchAll();
 } else {
   $lists=db()->query("SELECT id,title,description,visibility,position,updated_at FROM todo_lists WHERE is_archived=0 ORDER BY position,id")->fetchAll();
 }
 foreach($lists as &$list)$list['visibility_label']=visibility_label((string)$list['visibility']);
 json_response(['ok'=>true,'serverTime'=>gmdate('c'),'lists'=>$lists]);
}
if(preg_match('#^/api/todo-lists/(\d+)/state$#',$path,$m)){
 $u=require_auth();$id=(int)$m[1]; if(!list_access($id,$u,false)) json_response(['ok'=>false,'error'=>'Forbidden'],403);
 $st=db()->prepare('SELECT id,title,position FROM todo_categories WHERE todo_list_id=? ORDER BY position,id');$st->execute([$id]);$cats=$st->fetchAll();
 $st=db()->prepare('SELECT t.*,u.login author FROM tasks t JOIN users u ON u.id=t.created_by WHERE t.todo_list_id=? ORDER BY t.category_id,t.position,t.id');$st->execute([$id]);$tasks=$st->fetchAll();
 $st=db()->prepare('SELECT tt.task_id,g.id,g.name,g.color FROM task_tags tt JOIN tags g ON g.id=tt.tag_id JOIN tasks t ON t.id=tt.task_id WHERE t.todo_list_id=?');$st->execute([$id]);$tagRows=$st->fetchAll();$map=[];foreach($tagRows as $g)$map[$g['task_id']][]=['id'=>$g['id'],'name'=>$g['name'],'color'=>$g['color']];foreach($tasks as &$t)$t['tags']=$map[$t['id']]??[];
 json_response(['ok'=>true,'serverTime'=>gmdate('c'),'categories'=>$cats,'tasks'=>$tasks]);
}
if(preg_match('#^/api/public/todos/([A-Za-z0-9_-]{20,64})/state$#',$path,$m)){
 $st=db()->prepare("SELECT id FROM todo_lists WHERE public_slug=? AND visibility='PUBLIC_READ' AND is_archived=0");$st->execute([$m[1]]);$l=$st->fetch();if(!$l)json_response(['ok'=>false],404);$id=(int)$l['id'];$st=db()->prepare('SELECT id,title,position FROM todo_categories WHERE todo_list_id=? ORDER BY position,id');$st->execute([$id]);$cats=$st->fetchAll();$st=db()->prepare('SELECT t.id,t.category_id,t.title,t.description,t.position,t.updated_at,u.login author FROM tasks t JOIN users u ON u.id=t.created_by WHERE t.todo_list_id=? ORDER BY t.category_id,t.position,t.id');$st->execute([$id]);$tasks=$st->fetchAll();$st=db()->prepare('SELECT tt.task_id,g.id,g.name,g.color FROM task_tags tt JOIN tags g ON g.id=tt.tag_id JOIN tasks t ON t.id=tt.task_id WHERE t.todo_list_id=?');$st->execute([$id]);$map=[];foreach($st->fetchAll() as $g)$map[$g['task_id']][]=['id'=>$g['id'],'name'=>$g['name'],'color'=>$g['color']];foreach($tasks as &$t)$t['tags']=$map[$t['id']]??[];json_response(['ok'=>true,'categories'=>$cats,'tasks'=>$tasks]);
}
if(preg_match('#^/api/todo-lists/(\d+)/tasks$#',$path,$m) && $method==='POST'){
 $u=require_role('developer','founder');$id=(int)$m[1];if(!list_access($id,$u,true))json_response(['ok'=>false],403);$title=trim((string)($data['title']??''));validate_text_length($title,180,'Название');$cid=(int)($data['category_id']??0);if($title==='')json_response(['ok'=>false,'error'=>'Название обязательно'],422);$c=db()->prepare('SELECT 1 FROM todo_categories WHERE id=? AND todo_list_id=?');$c->execute([$cid,$id]);if(!$c->fetchColumn())json_response(['ok'=>false,'error'=>'Категория не найдена'],422);$desc=trim((string)($data['description']??''));validate_text_length($desc,20000,'Описание');$p=db()->prepare('SELECT COALESCE(MAX(position),-1)+1 FROM tasks WHERE category_id=?');$p->execute([$cid]);$pos=(int)$p->fetchColumn();db()->prepare('INSERT INTO tasks(todo_list_id,category_id,title,description,created_by,position) VALUES (?,?,?,?,?,?)')->execute([$id,$cid,$title,$desc,$u['id'],$pos]);$tid=(int)db()->lastInsertId();$taskTags=normalize_id_list($data['tags']??[],50);foreach($taskTags as $tag)db()->prepare('INSERT IGNORE INTO task_tags(task_id,tag_id) VALUES (?,?)')->execute([$tid,$tag]);audit($id,$tid,$u['id'],'TASK_CREATED',null,$cid,null,['title'=>$title]);event_log('TASK_CREATED',"{$u['login']} создал задачу «$title»",$u['id'],$id,$tid);json_response(['ok'=>true,'id'=>$tid],201);
}
if(preg_match('#^/api/tasks/(\d+)/move$#',$path,$m) && $method==='PATCH'){
 $u=require_role('developer','founder');$tid=(int)$m[1];$st=db()->prepare('SELECT * FROM tasks WHERE id=?');$st->execute([$tid]);$t=$st->fetch();if(!$t||!list_access((int)$t['todo_list_id'],$u,true))json_response(['ok'=>false],404);$to=(int)($data['category_id']??0);$c=db()->prepare('SELECT 1 FROM todo_categories WHERE id=? AND todo_list_id=?');$c->execute([$to,$t['todo_list_id']]);if(!$c->fetchColumn())json_response(['ok'=>false,'error'=>'Wrong category'],422);$pos=max(0,min(1000000,(int)($data['position']??0)));db()->prepare('UPDATE tasks SET category_id=?,position=?,version=version+1 WHERE id=?')->execute([$to,$pos,$tid]);audit((int)$t['todo_list_id'],$tid,$u['id'],'TASK_MOVED',(int)$t['category_id'],$to,['position'=>$t['position']],['position'=>$pos]);event_log('TASK_MOVED',"{$u['login']} переместил задачу «{$t['title']}»",$u['id'],(int)$t['todo_list_id'],$tid);json_response(['ok'=>true]);
}

if(preg_match('#^/api/tasks/(\d+)$#',$path,$m) && $method==='PATCH'){
 $u=require_role('developer','founder');$tid=(int)$m[1];$st=db()->prepare('SELECT * FROM tasks WHERE id=?');$st->execute([$tid]);$t=$st->fetch();if(!$t||!list_access((int)$t['todo_list_id'],$u,true))json_response(['ok'=>false],404);if($u['role']!=='founder' && (int)$t['created_by']!==(int)$u['id'])json_response(['ok'=>false,'error'=>'Редактировать чужие задачи нельзя'],403);$title=trim((string)($data['title']??$t['title']));$desc=trim((string)($data['description']??$t['description']));validate_text_length($title,180,'Название');validate_text_length($desc,20000,'Описание');if($title==='')json_response(['ok'=>false,'error'=>'Название обязательно'],422);db()->prepare('UPDATE tasks SET title=?,description=?,version=version+1 WHERE id=?')->execute([$title,$desc,$tid]);if(array_key_exists('tags',$data)){ $taskTags=normalize_id_list($data['tags'],50);db()->prepare('DELETE FROM task_tags WHERE task_id=?')->execute([$tid]);foreach($taskTags as $tag)db()->prepare('INSERT IGNORE INTO task_tags(task_id,tag_id) VALUES (?,?)')->execute([$tid,$tag]);}audit((int)$t['todo_list_id'],$tid,$u['id'],'TASK_UPDATED',null,null,['title'=>$t['title'],'description'=>$t['description']],['title'=>$title,'description'=>$desc]);event_log('TASK_UPDATED',"{$u['login']} изменил задачу «$title»",$u['id'],(int)$t['todo_list_id'],$tid);json_response(['ok'=>true]);
}
if(preg_match('#^/api/categories/(\d+)$#',$path,$m) && $method==='PATCH'){
 $u=require_role('founder');$cid=(int)$m[1];$st=db()->prepare('SELECT * FROM todo_categories WHERE id=?');$st->execute([$cid]);$c=$st->fetch();if(!$c)json_response(['ok'=>false],404);$title=trim((string)($data['title']??$c['title']));validate_text_length($title,120,'Название категории');if($title==='')json_response(['ok'=>false,'error'=>'Название категории обязательно'],422);$position=max(0,min(1000000,(int)($data['position']??$c['position'])));db()->prepare('UPDATE todo_categories SET title=?,position=? WHERE id=?')->execute([$title,$position,$cid]);audit((int)$c['todo_list_id'],null,$u['id'],'CATEGORY_UPDATED',null,null,$c,['title'=>$title,'position'=>$position]);json_response(['ok'=>true]);
}
if(preg_match('#^/api/categories/(\d+)$#',$path,$m) && $method==='DELETE'){
 $u=require_role('founder');$cid=(int)$m[1];$st=db()->prepare('SELECT * FROM todo_categories WHERE id=?');$st->execute([$cid]);$c=$st->fetch();if(!$c)json_response(['ok'=>false],404);$q=db()->prepare('SELECT COUNT(*) FROM tasks WHERE category_id=?');$q->execute([$cid]);if((int)$q->fetchColumn()>0)json_response(['ok'=>false,'error'=>'Нельзя удалить непустую категорию'],409);db()->prepare('DELETE FROM todo_categories WHERE id=?')->execute([$cid]);audit((int)$c['todo_list_id'],null,$u['id'],'CATEGORY_DELETED',null,null,$c,null);json_response(['ok'=>true]);
}
if(preg_match('#^/api/tags/(\d+)$#',$path,$m) && $method==='PATCH'){
 $u=require_role('founder');$id=(int)$m[1];$st=db()->prepare('SELECT * FROM tags WHERE id=?');$st->execute([$id]);$tag=$st->fetch();if(!$tag)json_response(['ok'=>false],404);$name=trim((string)($data['name']??$tag['name']));validate_text_length($name,80,'Название тега');if($name==='')json_response(['ok'=>false,'error'=>'Название тега обязательно'],422);db()->prepare('UPDATE tags SET name=? WHERE id=?')->execute([$name,$id]);json_response(['ok'=>true]);
}
if(preg_match('#^/api/tags/(\d+)$#',$path,$m) && $method==='DELETE'){
 require_role('founder');db()->prepare('DELETE FROM tags WHERE id=?')->execute([(int)$m[1]]);json_response(['ok'=>true]);
}
if(preg_match('#^/api/todo-lists/(\d+)$#',$path,$m) && $method==='PATCH'){
 $u=require_role('founder');$id=(int)$m[1];$st=db()->prepare('SELECT * FROM todo_lists WHERE id=?');$st->execute([$id]);$l=$st->fetch();if(!$l)json_response(['ok'=>false],404);$title=trim((string)($data['title']??$l['title']));$description=trim((string)($data['description']??$l['description']));validate_text_length($title,160,'Название списка');validate_text_length($description,20000,'Описание');if($title==='')json_response(['ok'=>false,'error'=>'Название списка обязательно'],422);$archived=!empty($data['is_archived'])?1:0;db()->prepare('UPDATE todo_lists SET title=?,description=?,is_archived=? WHERE id=?')->execute([$title,$description,$archived,$id]);audit($id,null,$u['id'],$archived?'TODO_LIST_ARCHIVED':'TODO_LIST_UPDATED',null,null,$l,['title'=>$title,'description'=>$description,'is_archived'=>$archived]);json_response(['ok'=>true]);
}

if(preg_match('#^/api/tasks/(\d+)$#',$path,$m) && $method==='DELETE'){
 $u=require_role('developer','founder');$tid=(int)$m[1];$st=db()->prepare('SELECT * FROM tasks WHERE id=?');$st->execute([$tid]);$t=$st->fetch();if(!$t||!list_access((int)$t['todo_list_id'],$u,true))json_response(['ok'=>false],404);if($u['role']!=='founder' && (int)$t['created_by']!==(int)$u['id'])json_response(['ok'=>false,'error'=>'Удалять чужие задачи нельзя'],403);$list=(int)$t['todo_list_id'];audit($list,$tid,$u['id'],'TASK_DELETED',(int)$t['category_id'],null,$t,null);event_log('TASK_DELETED',"{$u['login']} удалил задачу «{$t['title']}»",$u['id'],$list,$tid);db()->prepare('DELETE FROM tasks WHERE id=?')->execute([$tid]);json_response(['ok'=>true]);
}
if(preg_match('#^/api/todo-lists/(\d+)/categories$#',$path,$m) && $method==='POST'){
 $u=require_role('founder');$id=(int)$m[1];$title=trim((string)($data['title']??''));validate_text_length($title,120,'Название категории');if($title==='')json_response(['ok'=>false],422);$st=db()->prepare('SELECT COALESCE(MAX(position),-1)+1 FROM todo_categories WHERE todo_list_id=?');$st->execute([$id]);db()->prepare('INSERT INTO todo_categories(todo_list_id,title,position) VALUES (?,?,?)')->execute([$id,$title,(int)$st->fetchColumn()]);$newId=(int)db()->lastInsertId();audit($id,null,$u['id'],'CATEGORY_CREATED',null,null,null,['title'=>$title]);json_response(['ok'=>true,'category'=>['id'=>$newId,'title'=>$title]],201);
}
if($path==='/api/tags' && $method==='POST'){
 $u=require_role('founder');$name=trim((string)($data['name']??''));validate_text_length($name,80,'Название тега');if($name==='')json_response(['ok'=>false],422);try{db()->prepare('INSERT INTO tags(name,created_by) VALUES (?,?)')->execute([$name,$u['id']]);json_response(['ok'=>true,'tag'=>['id'=>(int)db()->lastInsertId(),'name'=>$name]],201);}catch(PDOException){json_response(['ok'=>false,'error'=>'Тег уже существует'],409);}
}
if(preg_match('#^/api/todo-lists/(\d+)/archive-settings$#',$path,$m) && $method==='PATCH'){
 $u=require_role('founder');$id=(int)$m[1];$st=db()->prepare('SELECT * FROM todo_lists WHERE id=? AND is_archived=1');$st->execute([$id]);$list=$st->fetch();if(!$list)json_response(['ok'=>false,'error'=>'Архивный To-do не найден'],404);
 $title=trim((string)($data['title']??$list['title']));$description=trim((string)($data['description']??$list['description']));$categoryTitles=is_array($data['category_titles']??null)?$data['category_titles']:[];$tagTitles=is_array($data['tag_titles']??null)?$data['tag_titles']:[];if(count($categoryTitles)>500||count($tagTitles)>500)json_response(['ok'=>false,'error'=>'Слишком много элементов в настройках'],422);
 validate_text_length($title,160,'Название списка');validate_text_length($description,20000,'Описание');if($title==='')json_response(['ok'=>false,'error'=>'Название списка обязательно'],422);
 foreach($categoryTitles as $name)validate_text_length(trim((string)$name),120,'Название категории');foreach($tagTitles as $name)validate_text_length(trim((string)$name),80,'Название тега');
 db()->beginTransaction();try{
   db()->prepare('UPDATE todo_lists SET title=?,description=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$title,$description,$id]);
   foreach($categoryTitles as $cid=>$name){$cid=(int)$cid;$name=trim((string)$name);if($cid>0&&$name!=='')db()->prepare('UPDATE todo_categories SET title=? WHERE id=? AND todo_list_id=?')->execute([$name,$cid,$id]);}
   foreach($tagTitles as $tagId=>$name){$tagId=(int)$tagId;$name=trim((string)$name);if($tagId>0&&$name!=='')db()->prepare('UPDATE tags SET name=? WHERE id=?')->execute([$name,$tagId]);}
   audit($id,null,$u['id'],'TODO_ARCHIVE_UPDATED',null,null,['title'=>$list['title'],'description'=>$list['description']],['title'=>$title,'description'=>$description]);
   event_log('TODO_LIST_UPDATED','Изменён архивный To-do список «'.$title.'»',$u['id'],$id);
   db()->commit();
 }catch(Throwable $e){db()->rollBack();throw $e;}
 json_response(['ok'=>true]);
}
if(preg_match('#^/api/todo-lists/(\d+)/settings$#',$path,$m) && $method==='PATCH'){
 $u=require_role('founder');$id=(int)$m[1];$st=db()->prepare('SELECT * FROM todo_lists WHERE id=?');$st->execute([$id]);$list=$st->fetch();if(!$list)json_response(['ok'=>false],404);
 $title=trim((string)($data['title']??$list['title']));$description=trim((string)($data['description']??$list['description']));$visibility=(string)($data['visibility']??$list['visibility']);
 validate_text_length($title,160,'Название списка');validate_text_length($description,20000,'Описание');if($title==='')json_response(['ok'=>false,'error'=>'Название списка обязательно'],422);if(!in_array($visibility,['TEAM_ONLY','SELECTED_USERS','PUBLIC_READ'],true))json_response(['ok'=>false,'error'=>'Некорректный режим видимости'],422);
 $viewerIds=normalize_id_list($data['viewer_ids']??[],500);$categoryTitles=is_array($data['category_titles']??null)?$data['category_titles']:[];$tagTitles=is_array($data['tag_titles']??null)?$data['tag_titles']:[];if(count($categoryTitles)>500||count($tagTitles)>500)json_response(['ok'=>false,'error'=>'Слишком много элементов в настройках'],422);foreach($categoryTitles as $name)validate_text_length(trim((string)$name),120,'Название категории');foreach($tagTitles as $name)validate_text_length(trim((string)$name),80,'Название тега');
 $slug=$visibility==='PUBLIC_READ'?($list['public_slug']?:bin2hex(random_bytes(16))):null;
 db()->beginTransaction();try{
   db()->prepare('UPDATE todo_lists SET title=?,description=?,visibility=?,public_slug=?,show_task_authors_publicly=1 WHERE id=?')->execute([$title,$description,$visibility,$slug,$id]);
   foreach($categoryTitles as $cid=>$name){$cid=(int)$cid;$name=trim((string)$name);if($cid>0&&$name!=='')db()->prepare('UPDATE todo_categories SET title=? WHERE id=? AND todo_list_id=?')->execute([$name,$cid,$id]);}
   foreach($tagTitles as $tagId=>$name){$tagId=(int)$tagId;$name=trim((string)$name);if($tagId>0&&$name!=='')db()->prepare('UPDATE tags SET name=? WHERE id=?')->execute([$name,$tagId]);}
   db()->prepare('DELETE FROM todo_list_viewers WHERE todo_list_id=?')->execute([$id]);
   if($visibility==='SELECTED_USERS'){$ins=db()->prepare("INSERT IGNORE INTO todo_list_viewers(todo_list_id,user_id,granted_by) SELECT ?,id,? FROM users WHERE id=? AND role='user' AND is_active=1");foreach($viewerIds as $uid)$ins->execute([$id,$u['id'],$uid]);}
   audit($id,null,$u['id'],'TODO_SETTINGS_UPDATED',null,null,['title'=>$list['title'],'visibility'=>$list['visibility']],['title'=>$title,'visibility'=>$visibility,'viewer_ids'=>$viewerIds]);
   db()->commit();
 }catch(Throwable $e){db()->rollBack();throw $e;}
 json_response(['ok'=>true,'public_url'=>$slug?absolute_url('/public/todos/'.$slug):null]);
}
if(preg_match('#^/api/todo-lists/(\d+)/visibility$#',$path,$m) && $method==='PATCH'){
 $u=require_role('founder');$id=(int)$m[1];$v=$data['visibility']??'TEAM_ONLY';if(!in_array($v,['TEAM_ONLY','SELECTED_USERS','PUBLIC_READ'],true))json_response(['ok'=>false,'error'=>'Некорректный режим видимости'],422);$st=db()->prepare('SELECT visibility,public_slug FROM todo_lists WHERE id=?');$st->execute([$id]);$old=$st->fetch();if(!$old)json_response(['ok'=>false],404);$slug=$v==='PUBLIC_READ'?($old['public_slug']?:bin2hex(random_bytes(16))):null;db()->prepare('UPDATE todo_lists SET visibility=?,public_slug=?,show_task_authors_publicly=1 WHERE id=?')->execute([$v,$slug,$id]);audit($id,null,$u['id'],$v==='PUBLIC_READ'?'TODO_PUBLIC_ENABLED':'TODO_ACCESS_UPDATED',null,null,$old,['visibility'=>$v]);json_response(['ok'=>true,'public_slug'=>$slug,'public_url'=>$slug?absolute_url('/public/todos/'.$slug):null]);
}
if(preg_match('#^/api/todo-lists/(\d+)/viewers$#',$path,$m)){
 $u=require_role('founder');$id=(int)$m[1];if($method==='GET'){$st=db()->prepare('SELECT u.id,u.login FROM todo_list_viewers v JOIN users u ON u.id=v.user_id WHERE v.todo_list_id=? ORDER BY u.login');$st->execute([$id]);json_response(['ok'=>true,'viewers'=>$st->fetchAll()]);}if($method==='POST'){$uid=(int)($data['user_id']??0);$valid=db()->prepare("SELECT 1 FROM users u JOIN todo_lists l ON l.id=? WHERE u.id=? AND u.role='user' AND u.is_active=1 AND l.is_archived=0");$valid->execute([$id,$uid]);if(!$valid->fetchColumn())json_response(['ok'=>false,'error'=>'Некорректный пользователь или список'],422);db()->prepare('INSERT IGNORE INTO todo_list_viewers(todo_list_id,user_id,granted_by) VALUES (?,?,?)')->execute([$id,$uid,$u['id']]);audit($id,null,$u['id'],'TODO_ACCESS_GRANTED',null,null,null,['user_id'=>$uid]);json_response(['ok'=>true]);}
}
if(preg_match('#^/api/todo-lists/(\d+)/viewers/(\d+)$#',$path,$m) && $method==='DELETE'){$u=require_role('founder');db()->prepare('DELETE FROM todo_list_viewers WHERE todo_list_id=? AND user_id=?')->execute([(int)$m[1],(int)$m[2]]);audit((int)$m[1],null,$u['id'],'TODO_ACCESS_REVOKED',null,null,null,['user_id'=>(int)$m[2]]);json_response(['ok'=>true]);}
json_response(['ok'=>false,'error'=>'API endpoint not found'],404);
