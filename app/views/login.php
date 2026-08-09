<section class="auth-card card">
  <div class="logo big">C</div><h1>Вход</h1><p class="muted">Внутренний портал команды</p>
  <?php if(!empty($error)):?><div class="alert danger" role="alert"><?=e($error)?></div><?php endif;?>
  <form method="post" id="login-form" class="stack">
    <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
    <label for="login-username">Логин<input id="login-username" name="login" required autocomplete="username webauthn"></label>
    <?php if(setting('password_login_enabled','1')==='1'):?>
      <label for="login-password">Пароль<input id="login-password" name="password" type="password" required autocomplete="current-password"></label>
      <button class="btn primary" type="submit">Войти</button>
    <?php else:?><p class="muted">Вход по паролю отключён администратором.</p><?php endif;?>
    <button class="btn <?=setting('password_login_enabled','1')==='1'?'ghost':'primary'?>" id="passkey-login-button" type="button">Войти через Passkey</button>
  </form>
  <?php if(setting('registration_enabled','1')==='1'):?><a class="text-link" href="<?=e(url('/register'))?>">Создать аккаунт</a><?php endif;?>
</section>
