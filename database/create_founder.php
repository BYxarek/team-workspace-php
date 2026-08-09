<?php
require dirname(__DIR__).'/app/bootstrap.php';
if (PHP_SAPI!=='cli') exit("CLI only
");
$login=$argv[1]??''; $password=$argv[2]??'';
if(!preg_match('/^[A-Za-z0-9_-]{3,32}$/',$login) || strlen($password)<8) exit("Usage: php database/create_founder.php LOGIN PASSWORD (min 8 chars)
");
$st=db()->prepare("INSERT INTO users(login,password_hash,role) VALUES (?,?, 'founder') ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),role='founder',is_active=1");
$st->execute([$login,password_hash($password,PASSWORD_ARGON2ID)]); echo "Founder ready: $login
";
