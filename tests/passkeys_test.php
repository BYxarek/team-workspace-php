<?php
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';
function config(string $key,mixed $default=null): mixed { return $key==='app.url'?'https://example.com/base':$default; }
function app_name(): string { return 'Test'; }
require dirname(__DIR__).'/app/passkeys.php';

if(!class_exists(\lbuchs\WebAuthn\WebAuthn::class))throw new RuntimeException('Composer WebAuthn class is missing.');
if(passkey_b64_decode(passkey_b64_encode("\x00\xfftest"))!=="\x00\xfftest")throw new RuntimeException('Base64url check failed.');
if(passkey_rp_id()!=='example.com'||passkey_origin()!=='https://example.com')throw new RuntimeException('RP check failed.');
$options=passkey_server()->getGetArgs([],300,true,true,true,true,true,'required');
if(!empty($options->publicKey->allowCredentials))throw new RuntimeException('Discoverable Passkey options must not identify accounts.');
$_SESSION=[];$challenge=new \lbuchs\WebAuthn\Binary\ByteBuffer(random_bytes(32));passkey_store_challenge('test',7,$challenge);
if(passkey_challenge_user('test')!==7||passkey_take_challenge('test')!==$challenge->getBinaryString())throw new RuntimeException('Challenge check failed.');
echo "Passkey checks passed\n";
