<header class="page-head"><div><h1>Журнал To-do действий</h1><p class="muted">История изменений рабочих досок.</p></div></header>
<?php require __DIR__.'/admin_nav.php'; ?>
<div class="admin-stack">
<section class="card">
  <form class="filters" method="get" action="<?=e(url('/admin/logs'))?>">
    <label class="sr-only" for="filter-list">To-do список</label><select id="filter-list" name="list" autocomplete="off"><option value="">Все доски</option><?php foreach($lists as $l):?><option value="<?=$l['id']?>" <?=($_GET['list']??'')==$l['id']?'selected':''?>><?=e($l['title'])?></option><?php endforeach;?></select>
    <label class="sr-only" for="filter-actor">Пользователь</label><select id="filter-actor" name="actor" autocomplete="off"><option value="">Все пользователи</option><?php foreach($actors as $a):?><option value="<?=$a['id']?>" <?=($_GET['actor']??'')==$a['id']?'selected':''?>><?=e($a['login'])?></option><?php endforeach;?></select>
    <label class="sr-only" for="filter-action">Тип действия</label><input id="filter-action" name="action" placeholder="Например TASK_MOVED" value="<?=e($_GET['action']??'')?>" autocomplete="off">
    <button class="btn" type="submit">Применить</button>
    <a class="btn ghost" href="<?=e(url('/admin/logs'))?>">Сбросить</a>
  </form>
  <div class="table-wrap"><table><thead><tr><th>Время</th><th>Пользователь</th><th>Доска</th><th>Действие</th><th>Задача</th><th>Из → В</th></tr></thead><tbody>
  <?php foreach($logs as $l):?><tr><td><?=e($l['created_at'])?></td><td><?=e($l['login'])?></td><td><?=e($l['list_title'])?></td><td><code><?=e($l['action'])?></code></td><td><?=e($l['task_title']??'—')?></td><td><?=e($l['from_title']??'—')?> → <?=e($l['to_title']??'—')?></td></tr><?php endforeach;?>
  <?php if(!$logs):?><tr><td colspan="6" class="muted">Записей по выбранным фильтрам нет.</td></tr><?php endif;?>
  </tbody></table></div>
  <?php if($totalPages>1):?><nav class="pagination" aria-label="Пагинация журнала">
    <?php $q=['list'=>$_GET['list']??'','actor'=>$_GET['actor']??'','action'=>$_GET['action']??'']; ?>
    <?php if($page>1):?><a class="btn small" href="<?=e(page_url('/admin/logs',$page-1,$q))?>">← Назад</a><?php endif;?>
    <span>Страница <?=$page?> из <?=$totalPages?> · <?=$totalLogs?> записей</span>
    <?php if($page<$totalPages):?><a class="btn small" href="<?=e(page_url('/admin/logs',$page+1,$q))?>">Вперёд →</a><?php endif;?>
  </nav><?php endif;?>
</section>
</div>
