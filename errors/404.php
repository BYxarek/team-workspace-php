<?php
require dirname(__DIR__).'/app/bootstrap.php';
render_standalone_error(404,'Страница не найдена','Запрошенный адрес не существует или был перемещён.');
