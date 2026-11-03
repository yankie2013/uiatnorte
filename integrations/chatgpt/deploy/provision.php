<?php
declare(strict_types=1);
// Offline only. No connection to the business database and no credential output.
if(PHP_SAPI!=='cli'||$argc!==3)exit(1);
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/src/OAuthCore.php';
$private=$argv[1];$runtime=$argv[2];
if(!is_dir($private)||!is_dir($runtime))exit(1);
$key=openssl_pkey_new(['private_key_bits'=>3072,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
if(!$key||!openssl_pkey_export($key,$pem))exit(1);
$details=openssl_pkey_get_details($key);
if(!$details||!file_put_contents($private.'/private.pem',$pem)||!file_put_contents($private.'/public.pem',$details['key']))exit(1);
$state=new Uiat\Connector\State(new PDO('sqlite:'.$runtime.'/oauth.sqlite'),new Uiat\Connector\Users(new PDO('sqlite::memory:')));
$state->createSchema();
