<?php
require dirname(__DIR__).'/app/bootstrap.php';
render_standalone_error(500,'Внутренняя ошибка','Сервер не смог обработать запрос. Попробуйте позже.');
