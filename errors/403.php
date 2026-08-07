<?php
require dirname(__DIR__).'/app/bootstrap.php';
render_standalone_error(403,'Доступ запрещён','Сервер не разрешает доступ к этому ресурсу.');
