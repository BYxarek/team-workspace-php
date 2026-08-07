<?php
$checks=[
 'PHP >= 8.2'=>version_compare(PHP_VERSION,'8.2.0','>='),
 'PDO'=>extension_loaded('pdo'),
 'pdo_mysql'=>extension_loaded('pdo_mysql'),
 'Argon2id'=>defined('PASSWORD_ARGON2ID'),
];
foreach($checks as $name=>$ok) echo ($ok?'[OK] ':'[FAIL] ').$name.PHP_EOL;
