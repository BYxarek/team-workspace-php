<header class="page-head">
  <div><h1>To-do списки</h1></div>
  <div class="actions">
    <span class="ajax-sync" id="todo-lists-sync" aria-live="polite"><span class="ajax-sync-dot"></span><span id="todo-lists-sync-text">AJAX · 5с</span></span>
    <?php if($user['role']==='founder'):?><button class="btn primary" type="button" data-open="new-list"><?=icon('plus')?> Создать список</button><?php endif;?>
  </div>
</header>
<section class="board-list" id="todo-lists-grid">
  <?php foreach($lists as $l):?>
    <a class="card board-link" href="<?=e(url('/todos/'.$l['id']))?>">
      <div><h3><?=e($l['title'])?></h3><p class="muted"><?=e($l['description'])?></p></div>
      <span class="visibility-badge"><?=e(visibility_label($l['visibility']))?></span>
    </a>
  <?php endforeach;?>
  <?php if(!$lists):?><div class="empty card">Доступных списков пока нет.</div><?php endif;?>
</section>
<?php if($user['role']==='founder'):?>
<dialog id="new-list" class="modal">
  <form method="post" action="<?=e(url('/todos'))?>" class="stack">
    <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
    <h2>Новый To-do список</h2>
    <label for="new-list-title">Название<input id="new-list-title" name="title" required autocomplete="off"></label>
    <label for="new-list-description">Описание<textarea id="new-list-description" name="description" autocomplete="off"></textarea></label>
    <div class="modal-actions"><button class="btn primary" type="submit">Создать</button><button type="button" class="btn ghost" data-close>Отмена</button></div>
  </form>
</dialog>
<?php endif;?>

<script>window.TODO_LISTS_PAGE=true;</script>
