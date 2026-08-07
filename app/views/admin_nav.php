<nav class="admin-nav" aria-label="Административная навигация">
  <a class="<?=request_path()==='/admin'?'active':''?>" href="<?=e(url('/admin'))?>"><?=icon('home')?> <span>Обзор</span></a>
  <a class="<?=request_path()==='/admin/users'?'active':''?>" href="<?=e(url('/admin/users'))?>"><?=icon('user')?> <span>Пользователи</span></a>
  <a class="<?=request_path()==='/admin/logs'?'active':''?>" href="<?=e(url('/admin/logs'))?>"><?=icon('log')?> <span>Журнал To-do</span></a>
  <a class="<?=str_starts_with(request_path(),'/admin/archive')?'active':''?>" href="<?=e(url('/admin/archive'))?>"><?=icon('tasks')?> <span>Архив To-do</span></a>
  <a class="<?=request_path()==='/admin/registration'?'active':''?>" href="<?=e(url('/admin/registration'))?>"><?=icon('settings')?> <span>Регистрация</span></a>
</nav>
