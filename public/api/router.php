<?php
declare(strict_types=1);
$u=current_user();$data=body_json();
if($_SERVER['REQUEST_METHOD']!=='GET') verify_csrf();


if($path==='/api/todo-lists' && $method==='GET'){
 $u=require_auth();
 if($u['role']==='user'){
   $st=db()->prepare("SELECT DISTINCT l.id,l.title,l.description,l.visibility,l.position,l.updated_at FROM todo_lists l LEFT JOIN todo_list_viewers v ON v.todo_list_id=l.id AND v.user_id=? WHERE l.is_archived=0 AND (l.visibility='PUBLIC_READ' OR (l.visibility='SELECTED_USERS' AND v.user_id IS NOT NULL)) ORDER BY l.position,l.id");
   $st->execute([$u['id']]);$lists=$st->fetchAll();
 } else {
   $lists=db()->query("SELECT id,title,description,visibility,position,updated_at FROM todo_lists WHERE is_archived=0 ORDER BY position,id")->fetchAll();
 }
 foreach($lists as &$list)$list['visibility_label']=visibility_label((string)$list['visibility']);
 json_response(['ok'=>true,'serverTime'=>gmdate('c'),'lists'=>$lists]);
}
if(preg_match('#^/api/todo-lists/(\d+)/state$#',$path,$m)){
 $id=(int)$m[1]; if(!list_access($id,$u,false)) json_response(['ok'=>false,'error'=>'Forbidden'],403);
 $st=db()->prepare('SELECT id,title,position FROM todo_categories WHERE todo_list_id=? ORDER BY position,id');$st->execute([$id]);$cats=$st->fetchAll();
 $st=db()->prepare('SELECT t.*,u.login author FROM tasks t JOIN users u ON u.id=t.created_by WHERE t.todo_list_id=? ORDER BY t.category_id,t.position,t.id');$st->execute([$id]);$tasks=$st->fetchAll();
 $st=db()->prepare('SELECT tt.task_id,g.id,g.name,g.color FROM task_tags tt JOIN tags g ON g.id=tt.tag_id JOIN tasks t ON t.id=tt.task_id WHERE t.todo_list_id=?');$st->execute([$id]);$tagRows=$st->fetchAll();$map=[];foreach($tagRows as $g)$map[$g['task_id']][]=['id'=>$g['id'],'name'=>$g['name'],'color'=>$g['color']];foreach($tasks as &$t)$t['tags']=$map[$t['id']]??[];
 json_response(['ok'=>true,'serverTime'=>gmdate('c'),'categories'=>$cats,'tasks'=>$tasks]);
}
if(preg_match('#^/api/public/todos/([A-Za-z0-9_-]{20,64})/state$#',$path,$m)){
 $st=db()->prepare("SELECT id FROM todo_lists WHERE public_slug=? AND visibility='PUBLIC_READ' AND is_archived=0");$st->execute([$m[1]]);$l=$st->fetch();if(!$l)json_response(['ok'=>false],404);$id=(int)$l['id'];$st=db()->prepare('SELECT id,title,position FROM todo_categories WHERE todo_list_id=? ORDER BY position,id');$st->execute([$id]);$cats=$st->fetchAll();$st=db()->prepare('SELECT t.id,t.category_id,t.title,t.description,t.position,t.updated_at,u.login author FROM tasks t JOIN users u ON u.id=t.created_by WHERE t.todo_list_id=? ORDER BY t.category_id,t.position,t.id');$st->execute([$id]);$tasks=$st->fetchAll();$st=db()->prepare('SELECT tt.task_id,g.id,g.name,g.color FROM task_tags tt JOIN tags g ON g.id=tt.tag_id JOIN tasks t ON t.id=tt.task_id WHERE t.todo_list_id=?');$st->execute([$id]);$map=[];foreach($st->fetchAll() as $g)$map[$g['task_id']][]=['id'=>$g['id'],'name'=>$g['name'],'color'=>$g['color']];foreach($tasks as &$t)$t['tags']=$map[$t['id']]??[];json_response(['ok'=>true,'categories'=>$cats,'tasks'=>$tasks]);
}
if(preg_match('#^/api/todo-lists/(\d+)/tasks$#',$path,$m) && $method==='POST'){
 $u=require_role('developer','founder');$id=(int)$m[1];if(!list_access($id,$u,true))json_response(['ok'=>false],403);$title=trim($data['title']??'');$cid=(int)($data['category_id']??0);if($title==='')json_response(['ok'=>false,'error'=>'Название обязательно'],422);$c=db()->prepare('SELECT 1 FROM todo_categories WHERE id=? AND todo_list_id=?');$c->execute([$cid,$id]);if(!$c->fetchColumn())json_response(['ok'=>false,'error'=>'Категория не найдена'],422);$p=db()->prepare('SELECT COALESCE(MAX(position),-1)+1 FROM tasks WHERE category_id=?');$p->execute([$cid]);$pos=(int)$p->fetchColumn();db()->prepare('INSERT INTO tasks(todo_list_id,category_id,title,description,created_by,position) VALUES (?,?,?,?,?,?)')->execute([$id,$cid,$title,trim($data['description']??''),$u['id'],$pos]);$tid=(int)db()->lastInsertId();foreach($data['tags']??[] as $tag)db()->prepare('INSERT IGNORE INTO task_tags(task_id,tag_id) VALUES (?,?)')->execute([$tid,(int)$tag]);audit($id,$tid,$u['id'],'TASK_CREATED',null,$cid,null,['title'=>$title]);event_log('TASK_CREATED',"{$u['login']} создал задачу «$title»",$u['id'],$id,$tid);json_response(['ok'=>true,'id'=>$tid],201);
}
if(preg_match('#^/api/tasks/(\d+)/move$#',$path,$m) && $method==='PATCH'){
 $u=require_role('developer','founder');$tid=(int)$m[1];$st=db()->prepare('SELECT * FROM tasks WHERE id=?');$st->execute([$tid]);$t=$st->fetch();if(!$t||!list_access((int)$t['todo_list_id'],$u,true))json_response(['ok'=>false],404);$to=(int)($data['category_id']??0);$c=db()->prepare('SELECT 1 FROM todo_categories WHERE id=? AND todo_list_id=?');$c->execute([$to,$t['todo_list_id']]);if(!$c->fetchColumn())json_response(['ok'=>false,'error'=>'Wrong category'],422);$pos=max(0,(int)($data['position']??0));db()->prepare('UPDATE tasks SET category_id=?,position=?,version=version+1 WHERE id=?')->execute([$to,$pos,$tid]);audit((int)$t['todo_list_id'],$tid,$u['id'],'TASK_MOVED',(int)$t['category_id'],$to,['position'=>$t['position']],['position'=>$pos]);event_log('TASK_MOVED',"{$u['login']} переместил задачу «{$t['title']}»",$u['id'],(int)$t['todo_list_id'],$tid);json_response(['ok'=>true]);
}
if(preg_match('#^/api/tasks/(\d+)$#',$path,$m) && $method==='PATCH'){
 $u=require_role('developer','founder');$tid=(int)$m[1];$st=db()->prepare('SELECT * FROM tasks WHERE id=?');$st->execute([$tid]);$t=$st->fetch();if(!$t)json_response(['ok'=>false],404);if($u['role']!=='founder' && (int)$t['created_by']!==(int)$u['id'])json_response(['ok'=>false,'error'=>'Редактировать чужие задачи нельзя'],403);$title=trim($data['title']??$t['title']);$desc=trim($data['description']??$t['description']);if($title==='')json_response(['ok'=>false,'error'=>'Название обязательно'],422);db()->prepare('UPDATE tasks SET title=?,description=?,version=version+1 WHERE id=?')->execute([$title,$desc,$tid]);if(isset($data['tags'])&&is_array($data['tags'])){db()->prepare('DELETE FROM task_tags WHERE task_id=?')->execute([$tid]);foreach($data['tags'] as $tag)db()->prepare('INSERT IGNORE INTO task_tags(task_id,tag_id) VALUES (?,?)')->execute([$tid,(int)$tag]);}audit((int)$t['todo_list_id'],$tid,$u['id'],'TASK_UPDATED',null,null,['title'=>$t['title'],'description'=>$t['description']],['title'=>$title,'description'=>$desc]);event_log('TASK_UPDATED',"{$u['login']} изменил задачу «$title»",$u['id'],(int)$t['todo_list_id'],$tid);json_response(['ok'=>true]);
}
if(preg_match('#^/api/categories/(\d+)$#',$path,$m) && $method==='PATCH'){
 $u=require_role('founder');$cid=(int)$m[1];$st=db()->prepare('SELECT * FROM todo_categories WHERE id=?');$st->execute([$cid]);$c=$st->fetch();if(!$c)json_response(['ok'=>false],404);$title=trim($data['title']??$c['title']);$position=max(0,(int)($data['position']??$c['position']));db()->prepare('UPDATE todo_categories SET title=?,position=? WHERE id=?')->execute([$title,$position,$cid]);audit((int)$c['todo_list_id'],null,$u['id'],'CATEGORY_UPDATED',null,null,$c,['title'=>$title,'position'=>$position]);json_response(['ok'=>true]);
}
if(preg_match('#^/api/categories/(\d+)$#',$path,$m) && $method==='DELETE'){
 $u=require_role('founder');$cid=(int)$m[1];$st=db()->prepare('SELECT * FROM todo_categories WHERE id=?');$st->execute([$cid]);$c=$st->fetch();if(!$c)json_response(['ok'=>false],404);$q=db()->prepare('SELECT COUNT(*) FROM tasks WHERE category_id=?');$q->execute([$cid]);if((int)$q->fetchColumn()>0)json_response(['ok'=>false,'error'=>'Нельзя удалить непустую категорию'],409);db()->prepare('DELETE FROM todo_categories WHERE id=?')->execute([$cid]);audit((int)$c['todo_list_id'],null,$u['id'],'CATEGORY_DELETED',null,null,$c,null);json_response(['ok'=>true]);
}
if(preg_match('#^/api/tags/(\d+)$#',$path,$m) && $method==='PATCH'){
 $u=require_role('founder');$id=(int)$m[1];$st=db()->prepare('SELECT * FROM tags WHERE id=?');$st->execute([$id]);$tag=$st->fetch();if(!$tag)json_response(['ok'=>false],404);$name=trim($data['name']??$tag['name']);db()->prepare('UPDATE tags SET name=? WHERE id=?')->execute([$name,$id]);json_response(['ok'=>true]);
}
if(preg_match('#^/api/tags/(\d+)$#',$path,$m) && $method==='DELETE'){
 require_role('founder');db()->prepare('DELETE FROM tags WHERE id=?')->execute([(int)$m[1]]);json_response(['ok'=>true]);
}
if(preg_match('#^/api/todo-lists/(\d+)$#',$path,$m) && $method==='PATCH'){
 $u=require_role('founder');$id=(int)$m[1];$st=db()->prepare('SELECT * FROM todo_lists WHERE id=?');$st->execute([$id]);$l=$st->fetch();if(!$l)json_response(['ok'=>false],404);$title=trim($data['title']??$l['title']);$description=trim($data['description']??$l['description']);$archived=!empty($data['is_archived'])?1:0;db()->prepare('UPDATE todo_lists SET title=?,description=?,is_archived=? WHERE id=?')->execute([$title,$description,$archived,$id]);audit($id,null,$u['id'],$archived?'TODO_LIST_ARCHIVED':'TODO_LIST_UPDATED',null,null,$l,['title'=>$title,'description'=>$description,'is_archived'=>$archived]);json_response(['ok'=>true]);
}
if(preg_match('#^/api/tasks/(\d+)$#',$path,$m) && $method==='DELETE'){
 $u=require_role('developer','founder');$tid=(int)$m[1];$st=db()->prepare('SELECT * FROM tasks WHERE id=?');$st->execute([$tid]);$t=$st->fetch();if(!$t)json_response(['ok'=>false],404);if($u['role']!=='founder' && (int)$t['created_by']!==(int)$u['id'])json_response(['ok'=>false,'error'=>'Удалять чужие задачи нельзя'],403);$list=(int)$t['todo_list_id'];audit($list,$tid,$u['id'],'TASK_DELETED',(int)$t['category_id'],null,$t,null);event_log('TASK_DELETED',"{$u['login']} удалил задачу «{$t['title']}»",$u['id'],$list,$tid);db()->prepare('DELETE FROM tasks WHERE id=?')->execute([$tid]);json_response(['ok'=>true]);
}
if(preg_match('#^/api/todo-lists/(\d+)/categories$#',$path,$m) && $method==='POST'){
 $u=require_role('founder');$id=(int)$m[1];$title=trim($data['title']??'');if($title==='')json_response(['ok'=>false],422);$st=db()->prepare('SELECT COALESCE(MAX(position),-1)+1 FROM todo_categories WHERE todo_list_id=?');$st->execute([$id]);db()->prepare('INSERT INTO todo_categories(todo_list_id,title,position) VALUES (?,?,?)')->execute([$id,$title,(int)$st->fetchColumn()]);$newId=(int)db()->lastInsertId();audit($id,null,$u['id'],'CATEGORY_CREATED',null,null,null,['title'=>$title]);json_response(['ok'=>true,'category'=>['id'=>$newId,'title'=>$title]],201);
}
if($path==='/api/tags' && $method==='POST'){
 $u=require_role('founder');$name=trim($data['name']??'');if($name==='')json_response(['ok'=>false],422);try{db()->prepare('INSERT INTO tags(name,created_by) VALUES (?,?)')->execute([$name,$u['id']]);json_response(['ok'=>true,'tag'=>['id'=>(int)db()->lastInsertId(),'name'=>$name]],201);}catch(PDOException){json_response(['ok'=>false,'error'=>'Тег уже существует'],409);}
}
if(preg_match('#^/api/todo-lists/(\d+)/archive-settings$#',$path,$m) && $method==='PATCH'){
 $u=require_role('founder');$id=(int)$m[1];$st=db()->prepare('SELECT * FROM todo_lists WHERE id=? AND is_archived=1');$st->execute([$id]);$list=$st->fetch();if(!$list)json_response(['ok'=>false,'error'=>'Архивный To-do не найден'],404);
 $title=trim((string)($data['title']??$list['title']));$description=trim((string)($data['description']??$list['description']));$categoryTitles=is_array($data['category_titles']??null)?$data['category_titles']:[];$tagTitles=is_array($data['tag_titles']??null)?$data['tag_titles']:[];
 if($title==='')json_response(['ok'=>false,'error'=>'Название списка обязательно'],422);
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
 if($title==='')json_response(['ok'=>false,'error'=>'Название списка обязательно'],422);if(!in_array($visibility,['TEAM_ONLY','SELECTED_USERS','PUBLIC_READ'],true))json_response(['ok'=>false,'error'=>'Некорректный режим видимости'],422);
 $viewerIds=array_values(array_unique(array_filter(array_map('intval',$data['viewer_ids']??[]),fn($v)=>$v>0)));$categoryTitles=is_array($data['category_titles']??null)?$data['category_titles']:[];$tagTitles=is_array($data['tag_titles']??null)?$data['tag_titles']:[];
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
 $u=require_role('founder');$id=(int)$m[1];if($method==='GET'){$st=db()->prepare('SELECT u.id,u.login FROM todo_list_viewers v JOIN users u ON u.id=v.user_id WHERE v.todo_list_id=? ORDER BY u.login');$st->execute([$id]);json_response(['ok'=>true,'viewers'=>$st->fetchAll()]);}if($method==='POST'){$uid=(int)($data['user_id']??0);db()->prepare('INSERT IGNORE INTO todo_list_viewers(todo_list_id,user_id,granted_by) VALUES (?,?,?)')->execute([$id,$uid,$u['id']]);audit($id,null,$u['id'],'TODO_ACCESS_GRANTED',null,null,null,['user_id'=>$uid]);json_response(['ok'=>true]);}
}
if(preg_match('#^/api/todo-lists/(\d+)/viewers/(\d+)$#',$path,$m) && $method==='DELETE'){$u=require_role('founder');db()->prepare('DELETE FROM todo_list_viewers WHERE todo_list_id=? AND user_id=?')->execute([(int)$m[1],(int)$m[2]]);audit((int)$m[1],null,$u['id'],'TODO_ACCESS_REVOKED',null,null,null,['user_id'=>(int)$m[2]]);json_response(['ok'=>true]);}
json_response(['ok'=>false,'error'=>'API endpoint not found'],404);
