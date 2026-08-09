<header class="page-head">
  <div><h1><?=e($list['title'])?></h1><p class="muted"><?=e($list['description'])?></p></div>
  <div class="actions">
    <span class="badge archive-badge">В архиве</span>
    <a class="btn" href="<?=e(url('/admin/archive'))?>">К архиву</a>
    <button class="btn primary" type="button" data-open="new-task"><?=icon('plus')?> Задача</button>
    <button class="btn" type="button" data-open="board-settings"><?=icon('settings')?> Редактировать</button>
    <form method="post" action="<?=e(url('/admin/archive'))?>"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?=$list['id']?>"><button class="btn primary" type="submit">Восстановить</button></form>
  </div>
</header>
<?php require __DIR__.'/admin_nav.php'; ?>
<section class="card archive-summary"><div><span class="muted">Статус</span><strong>В архиве</strong></div><div><span class="muted">Создатель</span><strong><?=e($list['creator'])?></strong></div><div><span class="muted">Видимость до архивации</span><strong><?=e(visibility_label($list['visibility']))?></strong></div></section>
<div id="board" class="kanban archive-kanban" data-list-id="<?=$list['id']?>"></div>

<dialog id="new-task" class="modal">
  <form id="new-task-form" class="stack" novalidate>
    <h2>Новая задача</h2>
    <label for="task-title">Название<input id="task-title" name="title" required autocomplete="off"></label>
    <label for="task-description">Описание<textarea id="task-description" name="description" autocomplete="off"></textarea></label>
    <label for="task-category">Категория<select id="task-category" name="category_id" autocomplete="off"><?php foreach($categories as $c):?><option value="<?=$c['id']?>"><?=e($c['title'])?></option><?php endforeach;?></select></label>
    <label for="task-tags">Теги<select id="task-tags" name="tags[]" multiple autocomplete="off"><?php foreach($tags as $t):?><option value="<?=$t['id']?>"><?=e($t['name'])?></option><?php endforeach;?></select></label>
    <div class="modal-actions"><button class="btn primary" type="submit">Создать</button><button type="button" class="btn ghost" data-close>Отмена</button></div>
  </form>
</dialog>

<dialog id="board-settings" class="modal wide settings-modal">
  <form id="board-settings-form" class="stack" novalidate>
    <div class="settings-head"><div><h2>Редактирование архивного To-do</h2><p class="muted">Настройки доступа недоступны, пока список находится в архиве.</p></div><button type="button" class="icon-btn" data-close aria-label="Закрыть">×</button></div>
    <section class="settings-section">
      <h3>Основные данные</h3>
      <label for="board-title">Название<input id="board-title" name="title" value="<?=e($list['title'])?>" required autocomplete="off"></label>
      <label for="board-description">Описание<textarea id="board-description" name="description" autocomplete="off"><?=e($list['description'])?></textarea></label>
    </section>
    <section class="settings-section">
      <div class="section-title"><h3>Категории</h3></div>
      <div class="inline add-row"><label class="sr-only" for="new-category-title">Новая категория</label><input id="new-category-title" name="new_category_title" placeholder="Новая категория" autocomplete="off"><button class="btn" type="button" id="add-category">Добавить</button></div>
      <div class="stack compact manager-list" id="category-manager"><?php foreach($categories as $c):?><div class="manager-row" data-category-row="<?=$c['id']?>"><label class="sr-only" for="category-title-<?=$c['id']?>">Название категории</label><input id="category-title-<?=$c['id']?>" name="category_titles[<?=$c['id']?>]" value="<?=e($c['title'])?>" data-cat-title="<?=$c['id']?>" autocomplete="off"><button type="button" class="btn small danger" data-cat-delete="<?=$c['id']?>">Удалить</button></div><?php endforeach;?></div>
    </section>
    <section class="settings-section">
      <div class="section-title"><h3>Теги</h3></div>
      <div class="inline add-row"><label class="sr-only" for="new-tag-name">Новый тег</label><input id="new-tag-name" name="new_tag_name" placeholder="Новый тег" autocomplete="off"><button class="btn" type="button" id="add-tag">Добавить</button></div>
      <div class="stack compact manager-list" id="tag-manager"><?php foreach($tags as $t):?><div class="manager-row" data-tag-row="<?=$t['id']?>"><label class="sr-only" for="tag-title-<?=$t['id']?>">Название тега</label><input id="tag-title-<?=$t['id']?>" name="tag_titles[<?=$t['id']?>]" value="<?=e($t['name'])?>" data-tag-title="<?=$t['id']?>" autocomplete="off"><button type="button" class="btn small danger" data-tag-delete="<?=$t['id']?>">Удалить</button></div><?php endforeach;?></div>
    </section>
    <div class="settings-savebar"><span class="muted" id="settings-status">Несохранённых изменений нет</span><div class="actions"><button type="button" class="btn ghost" data-close>Закрыть</button><button type="submit" class="btn primary" id="save-board-settings">Сохранить настройки</button></div></div>
  </form>
</dialog>
<script nonce="<?=e(csp_nonce())?>">window.BOARD={id:<?=$list['id']?>,canWrite:true,founder:true,archived:true,visibility:<?=json_for_script($list['visibility'])?>,tags:<?=json_for_script(array_map(fn($t)=>['id'=>(int)$t['id'],'name'=>$t['name']],$tags))?>};</script>
