<header class="page-head"><div><h1>Пользователи</h1><p class="muted">Управление ролями и доступом к порталу.</p></div><div class="actions"><button class="btn primary" type="button" data-open="create-user"><?=icon('plus')?> Создать пользователя</button></div></header>
<?php require __DIR__.'/admin_nav.php'; ?>
<div class="admin-stack">
<?php if(!empty($flashError)):?><div class="alert danger"><?=e($flashError)?></div><?php endif;?>
<?php if(!empty($flashSuccess)):?><div class="alert"><?=e($flashSuccess)?></div><?php endif;?>
<section class="card">
  <div class="section-title"><h2>Список пользователей</h2><span class="section-count"><?=$totalUsers?> всего</span></div>
  <div class="table-wrap"><table><thead><tr><th>Логин</th><th>Роль</th><th>Статус</th><th>Последняя активность</th><th>Действия</th></tr></thead><tbody>
  <?php foreach($users as $rowUser): $isSelf=(int)$rowUser['id']===(int)$user['id']; ?><tr>
    <td><strong><?=e($rowUser['login'])?></strong><?php if($isSelf):?> <span class="self-label">Вы</span><?php endif;?></td>
    <td><span class="role-label"><?=e(match($rowUser['role']){'founder'=>'Основатель','developer'=>'Разработчик',default=>'Пользователь'})?></span></td>
    <td><span class="status-pill <?=$rowUser['is_active']?'ok':'off'?>"><?=$rowUser['is_active']?'Активен':'Заблокирован'?></span></td>
    <td><?=!empty($rowUser['last_seen_at'])?e($rowUser['last_seen_at']):'—'?></td>
    <td>
      <?php if($isSelf):?>
        <span class="muted self-protected">Свою роль и статус изменять нельзя</span>
      <?php else:?>
      <form method="post" action="<?=e(url('/admin/users'))?>" class="inline admin-user-form">
        <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="action" value="user_update">
        <input type="hidden" name="id" value="<?=$rowUser['id']?>">
        <label class="sr-only" for="role-<?=$rowUser['id']?>">Роль</label>
        <select id="role-<?=$rowUser['id']?>" name="role" autocomplete="off"><option value="user" <?=$rowUser['role']==='user'?'selected':''?>>Пользователь</option><option value="developer" <?=$rowUser['role']==='developer'?'selected':''?>>Разработчик</option><option value="founder" <?=$rowUser['role']==='founder'?'selected':''?>>Основатель</option></select>
        <label class="sr-only" for="active-<?=$rowUser['id']?>">Статус</label>
        <select id="active-<?=$rowUser['id']?>" name="is_active" autocomplete="off"><option value="1" <?=$rowUser['is_active']?'selected':''?>>Активен</option><option value="0" <?=!$rowUser['is_active']?'selected':''?>>Заблокирован</option></select>
        <button class="btn small" type="submit">Сохранить</button>
      </form>
      <?php endif;?>
    </td>
  </tr><?php endforeach;?>
  </tbody></table></div>
  <?php if($totalPages>1):?><nav class="pagination" aria-label="Пагинация пользователей">
    <?php if($page>1):?><a class="btn small" href="<?=e(page_url('/admin/users',$page-1))?>">← Назад</a><?php endif;?>
    <span>Страница <?=$page?> из <?=$totalPages?></span>
    <?php if($page<$totalPages):?><a class="btn small" href="<?=e(page_url('/admin/users',$page+1))?>">Вперёд →</a><?php endif;?>
  </nav><?php endif;?>
</section>
</div>
<dialog id="create-user" class="modal">
  <form method="post" action="<?=e(url('/admin/users'))?>" class="stack">
    <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
    <input type="hidden" name="action" value="user_create">
    <h2>Создать пользователя</h2>
    <label for="admin-create-login">Логин<input id="admin-create-login" name="login" required minlength="3" maxlength="32" autocomplete="username"></label>
    <label for="admin-create-password">Пароль<input id="admin-create-password" name="password" type="password" required minlength="8" autocomplete="new-password"></label>
    <label for="admin-create-role">Роль<select id="admin-create-role" name="role" autocomplete="off"><option value="user">Пользователь</option><option value="developer">Разработчик</option><option value="founder">Основатель</option></select></label>
    <div class="modal-actions"><button class="btn primary" type="submit">Создать</button><button type="button" class="btn ghost" data-close>Отмена</button></div>
  </form>
</dialog>
