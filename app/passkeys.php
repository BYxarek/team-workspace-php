<?php
declare(strict_types=1);

use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;

function passkey_b64_encode(string $value): string { return rtrim(strtr(base64_encode($value),'+/','-_'),'='); }
function passkey_b64_decode(string $value): string {
    $decoded=base64_decode(strtr($value,'-_','+/').str_repeat('=',(4-strlen($value)%4)%4),true);
    if($decoded===false) throw new InvalidArgumentException('Некорректные данные Passkey.');
    return $decoded;
}
function passkey_rp_id(): string {
    $host=(string)parse_url((string)config('app.url',''),PHP_URL_HOST);
    if($host==='') throw new RuntimeException('В app.url не указан домен для Passkey.');
    return strtolower($host);
}
function passkey_origin(): string {
    $url=(string)config('app.url','');$scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));$host=passkey_rp_id();$port=parse_url($url,PHP_URL_PORT);
    return $scheme.'://'.$host.($port && !(($scheme==='https'&&$port===443)||($scheme==='http'&&$port===80))?':'.$port:'');
}
function passkey_server(): WebAuthn { return new WebAuthn(app_name(),passkey_rp_id(),['none'],true); }
function passkey_store_challenge(string $purpose,int $userId,ByteBuffer $challenge): void {
    $_SESSION['passkey_challenges'][$purpose]=['challenge'=>passkey_b64_encode($challenge->getBinaryString()),'user_id'=>$userId,'expires'=>time()+300];
}
function passkey_challenge_user(string $purpose): int {
    $row=$_SESSION['passkey_challenges'][$purpose]??null;
    if(!is_array($row)||(int)($row['expires']??0)<time()) throw new InvalidArgumentException('Запрос Passkey истёк. Повторите попытку.');
    return (int)($row['user_id']??0);
}
function passkey_take_challenge(string $purpose): string {
    $row=$_SESSION['passkey_challenges'][$purpose]??null;unset($_SESSION['passkey_challenges'][$purpose]);
    if(!is_array($row)||(int)($row['expires']??0)<time()) throw new InvalidArgumentException('Запрос Passkey истёк. Повторите попытку.');
    return passkey_b64_decode((string)$row['challenge']);
}
function passkey_client_data(string $encoded): string {
    $raw=passkey_b64_decode($encoded);
    try{$data=json_decode($raw,true,16,JSON_THROW_ON_ERROR);}catch(JsonException){throw new InvalidArgumentException('Некорректный ответ Passkey.');}
    if(!hash_equals(passkey_origin(),(string)($data['origin']??''))||!empty($data['crossOrigin']))throw new InvalidArgumentException('Passkey получен с другого сайта.');
    return $raw;
}
