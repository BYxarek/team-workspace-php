<header class="page-head"><div><h1>Регистрация и вход</h1><p class="muted">Управление созданием аккаунтов и способами авторизации.</p></div></header>
<?php require __DIR__.'/admin_nav.php'; ?>
<?php if(!empty($flashError)):?><div class="alert danger" role="alert"><?=e($flashError)?></div><?php endif;?>
<div class="admin-stack">
<section class="card narrow">
  <h2>Регистрация пользователей</h2>
  <p>Текущий статус: <span class="status-pill <?=setting('registration_enabled','1')==='1'?'ok':'off'?>"><?=setting('registration_enabled','1')==='1'?'Включена':'Отключена'?></span></p>
  <p class="muted">При отключении существующие аккаунты продолжат работать, но создать новый аккаунт через форму регистрации будет нельзя.</p>
  <form method="post" action="<?=e(url('/admin/registration'))?>"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle_registration"><button class="btn <?=setting('registration_enabled','1')==='1'?'danger':'primary'?>" type="submit"><?=setting('registration_enabled','1')==='1'?'Отключить регистрацию':'Включить регистрацию'?></button></form>
</section>
<section class="card narrow">
  <h2>Вход по паролю</h2>
  <p>Текущий статус: <span class="status-pill <?=setting('password_login_enabled','1')==='1'?'ok':'off'?>"><?=setting('password_login_enabled','1')==='1'?'Включён':'Отключён'?></span></p>
  <p class="muted">При отключении войти можно будет только через Passkey. В регистрации поле пароля останется, а новый пользователь сразу должен будет добавить Passkey.</p>
  <?php if($missingPasskeys>0):?><div class="alert">Активных пользователей без Passkey: <?=e((string)$missingPasskeys)?>. Отключение пароля станет доступно, когда каждый добавит Passkey.</div><?php endif;?>
  <form method="post" action="<?=e(url('/admin/registration'))?>"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle_password_login"><button class="btn <?=setting('password_login_enabled','1')==='1'?'danger':'primary'?>" type="submit" <?=setting('password_login_enabled','1')==='1'&&$missingPasskeys>0?'disabled':''?>><?=setting('password_login_enabled','1')==='1'?'Отключить вход по паролю':'Включить вход по паролю'?></button></form>
</section>
</div>
