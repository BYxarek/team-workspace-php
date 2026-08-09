<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$checks=[
 'PHP >= 8.2'=>version_compare(PHP_VERSION,'8.2.0','>='),
 'PDO'=>extension_loaded('pdo'),
 'pdo_mysql'=>extension_loaded('pdo_mysql'),
 'OpenSSL'=>extension_loaded('openssl'),
 'Composer autoload'=>is_file(__DIR__.'/vendor/autoload.php'),
 'WebAuthn library'=>is_file(__DIR__.'/vendor/lbuchs/webauthn/src/WebAuthn.php'),
 'Argon2id'=>defined('PASSWORD_ARGON2ID'),
];
foreach($checks as $name=>$ok) echo ($ok?'[OK] ':'[FAIL] ').$name.PHP_EOL;
