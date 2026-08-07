<section class="auth-card card">
  <div class="logo big">C</div>
  <h1>Регистрация</h1>

  <?php if(!empty($error)):?>
    <div class="alert danger" role="alert"><?=e($error)?></div>
  <?php endif;?>

  <?php if(setting('registration_enabled','1')!=='1'):?>
    <div class="alert">Регистрация временно закрыта.</div>
  <?php else:?>
    <form method="post" class="stack" id="register-form" novalidate>
      <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">

      <label for="register-login">
        Логин
        <input
          id="register-login"
          name="login"
          value="<?=e($_POST['login'] ?? '')?>"
          required
          minlength="3"
          maxlength="32"
          autocomplete="username"
          aria-describedby="login-help login-warning"
        >
        <small class="field-help" id="login-help">3–32 символа. Разрешены только латинские буквы, цифры, <code>_</code> и <code>-</code>.</small>
        <span class="field-warning" id="login-warning" role="alert" aria-live="polite" hidden></span>
      </label>

      <label for="register-password">
        Пароль
        <input id="register-password" name="password" type="password" minlength="8" required autocomplete="new-password">
      </label>

      <label for="register-password-confirmation">
        Повтор пароля
        <input id="register-password-confirmation" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password">
      </label>

      <button class="btn primary">Зарегистрироваться</button>
    </form>
  <?php endif;?>

  <a class="text-link" href="<?=e(url('/login'))?>">Вернуться ко входу</a>
</section>
