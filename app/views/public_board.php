<?php $siteName=app_name(); ?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?=e($list['title'])?> · <?=e($siteName)?></title>
  <link rel="stylesheet" href="<?=e(url('/assets/app.css'))?>">
</head>
<body class="public-only-body">
  <main class="public-only-wrap">
    <header class="public-only-title">
      <h1><?=e($list['title'])?></h1>
      <?php if(trim((string)$list['description'])!==''):?><p><?=e($list['description'])?></p><?php endif;?>
    </header>
    <div id="board" class="kanban public" data-public-slug="<?=e($list['public_slug'])?>"></div>
  </main>
  <div id="toast" class="toast"></div>
  <script nonce="<?=e(csp_nonce())?>">window.CSRF='';window.APP_BASE=<?=json_for_script(base_path())?>;window.PUBLIC_BOARD={slug:<?=json_for_script($list['public_slug'])?>};</script>
  <script src="<?=e(url('/assets/app.js'))?>"></script>
</body>
</html>
