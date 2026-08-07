<header class="page-head"><div><h1>Архив To-do</h1><p class="muted">Архивные списки доступны только основателю. Их можно просмотреть и восстановить.</p></div></header>
<?php require __DIR__.'/admin_nav.php'; ?>
<?php if($flashError):?><div class="alert error"><?=e($flashError)?></div><?php endif;?>
<?php if($flashSuccess):?><div class="alert success"><?=e($flashSuccess)?></div><?php endif;?>
<section class="card admin-section-card">
  <div class="section-title"><h2>Архивные списки</h2><span class="section-count"><?=number_format($totalArchived,0,'',' ')?> всего</span></div>
  <?php if(!$archivedLists):?>
    <div class="empty-inline">В архиве пока нет To-do списков.</div>
  <?php else:?>
    <div class="archive-list">
      <?php foreach($archivedLists as $list):?>
        <article class="archive-item">
          <div class="archive-main">
            <h3><?=e($list['title'])?></h3>
            <?php if($list['description']):?><p class="muted"><?=e($list['description'])?></p><?php endif;?>
            <div class="archive-meta"><span>Создал: <?=e($list['creator'])?></span><span>Категорий: <?=number_format((int)$list['category_count'],0,'',' ')?></span><span>Задач: <?=number_format((int)$list['task_count'],0,'',' ')?></span></div>
          </div>
          <div class="actions archive-actions">
            <a class="btn" href="<?=e(url('/admin/archive/'.$list['id']))?>">Просмотреть</a>
            <form method="post" action="<?=e(url('/admin/archive'))?>">
              <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
              <input type="hidden" name="action" value="restore">
              <input type="hidden" name="id" value="<?=$list['id']?>">
              <button class="btn primary" type="submit">Восстановить</button>
            </form>
          </div>
        </article>
      <?php endforeach;?>
    </div>
    <?php if($totalPages>1):?><nav class="pagination" aria-label="Пагинация архива">
      <?php if($page>1):?><a class="btn small" href="<?=e(page_url('/admin/archive',$page-1))?>">Назад</a><?php endif;?>
      <span>Страница <?=$page?> из <?=$totalPages?></span>
      <?php if($page<$totalPages):?><a class="btn small" href="<?=e(page_url('/admin/archive',$page+1))?>">Далее</a><?php endif;?>
    </nav><?php endif;?>
  <?php endif;?>
</section>
