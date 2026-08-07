<header class="page-head"><div><h1>Регистрация</h1><p class="muted">Управление созданием новых аккаунтов.</p></div></header>
<?php require __DIR__.'/admin_nav.php'; ?>
<div class="admin-stack">
<section class="card narrow">
  <h2>Регистрация пользователей</h2>
  <p>Текущий статус: <span class="status-pill <?=setting('registration_enabled','1')==='1'?'ok':'off'?>"><?=setting('registration_enabled','1')==='1'?'Включена':'Отключена'?></span></p>
  <p class="muted">При отключении существующие аккаунты продолжат работать, но создать новый аккаунт через форму регистрации будет нельзя.</p>
  <form method="post" action="<?=e(url('/admin/registration'))?>">
    <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
    <input type="hidden" name="action" value="toggle_registration">
    <button class="btn <?=setting('registration_enabled','1')==='1'?'danger':'primary'?>" type="submit"><?=setting('registration_enabled','1')==='1'?'Отключить регистрацию':'Включить регистрацию'?></button>
  </form>
</section>
</div>
