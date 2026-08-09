<header class="page-head"><div><h1>Профиль</h1></div></header>
<?php if(!empty($_SESSION['passkey_setup_required'])):?><div class="alert danger" role="alert">Чтобы завершить создание аккаунта, добавьте хотя бы один Passkey.</div><?php endif;?>
<div class="profile-stack">
  <section class="card narrow">
    <dl class="info"><dt>Логин</dt><dd><?=e($user['login'])?></dd><dt>Роль</dt><dd><span class="badge"><?=e($user['role'])?></span></dd><dt>Создан</dt><dd><?=e($user['created_at'])?></dd></dl>
  </section>
  <section class="card narrow">
    <div class="profile-card-head"><div><h2>Passkey</h2><p class="muted">Отпечаток пальца, распознавание лица, PIN устройства или аппаратный ключ.</p></div><button class="btn primary" type="button" data-open="passkey-dialog">Добавить Passkey</button></div>
    <div class="stack" id="passkey-list">
      <?php foreach($passkeys as $passkey):?>
        <div class="manager-row" data-passkey-row="<?=e((string)$passkey['id'])?>"><div><b><?=e($passkey['name'])?></b><small class="field-help">Добавлен <?=e($passkey['created_at'])?><?php if($passkey['last_used_at']):?> · использован <?=e($passkey['last_used_at'])?><?php endif;?></small></div><button class="btn small danger" type="button" data-passkey-delete="<?=e((string)$passkey['id'])?>">Удалить</button></div>
      <?php endforeach;?>
      <?php if(!$passkeys):?><p class="muted" id="passkey-empty">Passkey пока не добавлены.</p><?php endif;?>
    </div>
  </section>
  <section class="card narrow">
    <div class="profile-card-head"><div><h2>Пароль</h2><p class="muted">Измените пароль аккаунта.</p></div><button class="btn" type="button" data-open="password-dialog">Сменить пароль</button></div>
    <?php if(!empty($profileSuccess)):?><div class="alert success"><?=e($profileSuccess)?></div><?php endif;?>
  </section>
</div>

<dialog id="passkey-dialog" class="modal" <?=!empty($_SESSION['passkey_setup_required'])?'data-auto-open':''?>>
  <form id="passkey-register-form" class="stack">
    <div class="profile-card-head"><h2>Добавить Passkey</h2><button type="button" class="icon-btn" data-close aria-label="Закрыть">×</button></div>
    <label for="passkey-name">Имя Passkey<input id="passkey-name" name="name" required maxlength="80" autocomplete="off" placeholder="Например, рабочий ноутбук"></label>
    <div class="modal-actions"><button class="btn primary" type="submit">Добавить</button><button class="btn ghost" type="button" data-close>Отмена</button></div>
  </form>
</dialog>

<dialog id="password-dialog" class="modal" <?=!empty($profileError)?'data-auto-open':''?>>
  <form method="post" class="stack">
    <div class="profile-card-head"><h2>Сменить пароль</h2><button type="button" class="icon-btn" data-close aria-label="Закрыть">×</button></div>
    <?php if(!empty($profileError)):?><div class="alert danger"><?=e($profileError)?></div><?php endif;?>
    <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="password">
    <label for="profile-current-password">Текущий пароль<input id="profile-current-password" type="password" name="current_password" required autocomplete="current-password"></label>
    <label for="profile-new-password">Новый пароль<input id="profile-new-password" type="password" name="password" minlength="8" required autocomplete="new-password"></label>
    <div class="modal-actions"><button class="btn primary" type="submit">Обновить пароль</button><button class="btn ghost" type="button" data-close>Отмена</button></div>
  </form>
</dialog>
