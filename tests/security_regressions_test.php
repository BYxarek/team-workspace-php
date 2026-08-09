<?php
declare(strict_types=1);

$router=file_get_contents(dirname(__DIR__).'/public/api/router.php');
$helpers=file_get_contents(dirname(__DIR__).'/app/helpers.php');
$index=file_get_contents(dirname(__DIR__).'/public/index.php');
if(false===$router||false===$helpers||false===$index)throw new RuntimeException('Unable to read security boundaries.');

$move=substr($router,(int)strpos($router,"/api/tasks/(\\d+)/move"),1800);
if(!str_contains($move,'created_by\']!==(int)$u[\'id\']'))throw new RuntimeException('Task move ownership guard is missing.');
$public=substr($router,(int)strpos($router,'/api/public/todos/'),2200);
if(str_contains($public,'login author'))throw new RuntimeException('Public board exposes account names.');
if(!str_contains($router,'getGetArgs([],')||str_contains(substr($router,0,(int)strpos($router,"if(\$path==='/api/passkeys/login'")),'$data[\'login\']'))throw new RuntimeException('Passkey discovery identifies accounts.');
if(!str_contains($helpers,'function rate_limit_consume')||str_contains($index,'rate_limit_retry_after('))throw new RuntimeException('Rate limits are not consumed atomically before authentication.');
if(!str_contains($helpers,'$_SESSION[\'auth_version\']')||!str_contains($index,'auth_version=auth_version+1'))throw new RuntimeException('Credential changes do not revoke old sessions.');
if(!str_contains($helpers,'$u=null; return null;'))throw new RuntimeException('Rejected users remain cached inside the request.');
if(!str_contains($router,'require_recent_auth()'))throw new RuntimeException('Passkey enrollment lacks recent authentication.');

echo "Security regression checks passed\n";
