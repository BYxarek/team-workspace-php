<header class="page-head"><div><h1>Админ-панель</h1><p class="muted">Состояние внутреннего портала и команды.</p></div></header>
<?php require __DIR__.'/admin_nav.php'; ?>
<div class="admin-stack">
  <section class="stats-grid admin-stats">
    <article class="card stat-card"><span class="stat-icon"><?=icon('user')?></span><span class="muted">Всего пользователей</span><strong><?=number_format($stats['users'],0,'',' ')?></strong></article>
    <article class="card stat-card"><span class="stat-icon online"><?=icon('user')?></span><span class="muted">Сейчас онлайн</span><strong><?=number_format($stats['online'],0,'',' ')?></strong><small class="muted">Активность за 5 минут</small></article>
    <article class="card stat-card"><span class="stat-icon"><?=icon('log')?></span><span class="muted">Действий сегодня</span><strong><?=number_format($stats['actions_today'],0,'',' ')?></strong></article>
    <article class="card stat-card"><span class="stat-icon"><?=icon('tasks')?></span><span class="muted">Всего задач</span><strong><?=number_format($stats['tasks'],0,'',' ')?></strong></article>
    <article class="card stat-card"><span class="stat-icon"><?=icon('tasks')?></span><span class="muted">Активных To-do списков</span><strong><?=number_format($stats['lists'],0,'',' ')?></strong></article>
    <article class="card stat-card"><span class="stat-icon"><?=icon('shield')?></span><span class="muted">Разработчиков</span><strong><?=number_format($stats['developers'],0,'',' ')?></strong></article>
    <article class="card stat-card"><span class="stat-icon"><?=icon('user')?></span><span class="muted">Обычных пользователей</span><strong><?=number_format($stats['regular_users'],0,'',' ')?></strong></article>
  </section>
  <section class="card online-panel"><div class="section-title"><h2>Сейчас онлайн</h2><span class="section-count"><?=count($onlineUsers)?> активных</span></div><div class="online-users"><?php if(!$onlineUsers):?><span class="muted">Активных пользователей за последние 5 минут нет.</span><?php endif;?><?php foreach($onlineUsers as $ou):?><div class="online-user"><span class="online-dot"></span><div><strong><?=e($ou['login'])?></strong><small><?=e($ou['role'])?></small></div></div><?php endforeach;?></div></section>
</div>
