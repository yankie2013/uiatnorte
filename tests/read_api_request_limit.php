<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/app/Api/RequestLimiter.php';
use App\Api\RequestLimiter;
$dir=sys_get_temp_dir().'/uiat-limit-'.bin2hex(random_bytes(8));mkdir($dir,0700);
$secret=bin2hex(random_bytes(32));$n=0;
function verify(bool $v):void {global $n;if(!$v)throw new RuntimeException('Assertion failed');$n++;}
try {
 for($i=0;$i<300;$i++) verify(RequestLimiter::consume($dir,'192.0.2.1',$secret,120));
 verify(!RequestLimiter::consume($dir,'192.0.2.1',$secret,121));
 verify(RequestLimiter::consume($dir,'192.0.2.2',$secret,121));
 verify(RequestLimiter::consume($dir,'192.0.2.1',$secret,180));
 verify(RequestLimiter::consume($dir,'2001:db8::1',$secret,180));
 $path=$dir.'/request-rate.json';$state=json_decode(file_get_contents($path),true);
 verify(count($state['counts'])===2);
 verify(RequestLimiter::consume($dir,'2001:0db8:0000:0000:0000:0000:0000:0001',$secret,180));
 verify(count(json_decode(file_get_contents($path),true)['counts'])===2);
 $raw=file_get_contents($path);verify(!str_contains($raw,'192.0.2.')&&!str_contains($raw,$secret)&&!str_contains($raw,'2001:'));
 verify((fileperms($path)&0777)===0600);
 file_put_contents($path,'INVALID');
 try {RequestLimiter::consume($dir,'192.0.2.1',$secret,180);throw new RuntimeException('Expected failure');}catch(JsonException){verify(true);}
 $counts=[];for($i=0;$i<4096;$i++)$counts[hash('sha256',(string)$i)]=1;
 file_put_contents($path,json_encode(['window'=>3,'counts'=>$counts]));
 verify(!RequestLimiter::consume($dir,'192.0.2.1',$secret,180));
 echo "$n comprobaciones correctas: límite, renovación, IPv6, privacidad, permisos, corrupción y capacidad.\n";
} finally {if(file_exists($dir.'/request-rate.json'))unlink($dir.'/request-rate.json');rmdir($dir);}
