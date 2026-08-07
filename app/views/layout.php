<?php $siteName=app_name(); $title=$title??$siteName; $siteInitial=mb_strtoupper(mb_substr($siteName,0,1)); ?>
<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> · <?=e($siteName)?></title><link rel="stylesheet" href="<?=e(url('/assets/app.css'))?>"></head><body>
<div class="ambient a1"></div><div class="ambient a2"></div>
<?php if($user): ?><aside class="sidebar"><a class="brand" href="<?=e(url('/dashboard'))?>"><span class="logo"><?=e($siteInitial)?></span><b><?=e($siteName)?></b></a><nav>
<a href="<?=e(url('/dashboard'))?>"><?=icon('home')?> <span>Главная</span></a>
<?php if(role_rank($user['role'])>=2 || $user['role']==='user'): ?><a href="<?=e(url('/todos'))?>"><?=icon('tasks')?> <span>To-do</span></a><?php endif; ?>
<?php if($user['role']==='founder'): ?><a href="<?=e(url('/admin'))?>"><?=icon('shield')?> <span>Админ</span></a><?php endif; ?>
<a href="<?=e(url('/profile'))?>"><?=icon('user')?> <span>Профиль</span></a></nav><form method="post" action="<?=e(url('/logout'))?>"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><button class="nav-btn" type="submit"><?=icon('logout')?> <span>Выйти</span></button></form></aside><?php endif; ?>
<main class="<?= $user?'app-main':'guest-main' ?>"><?php require $contentView; ?></main><div id="toast" class="toast"></div><script>window.CSRF='<?=e(csrf_token())?>';window.APP_BASE=<?=json_encode(base_path(),JSON_UNESCAPED_SLASHES)?>;</script><script src="<?=e(url('/assets/app.js'))?>"></script></body></html>