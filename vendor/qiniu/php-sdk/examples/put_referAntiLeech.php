<?php
require_once __DIR__ . '/../autoload.php';

use Qiniu\Auth;

$accessKey = getenv('QINIU_ACCESS_KEY');
$secretKey = getenv('QINIU_SECRET_KEY');

$auth = new Auth($accessKey, $secretKey);
$config = new \Qiniu\Config();
$bucketManager = new \Qiniu\Storage\BucketManager($auth, $config);

$bucket = 'xxxx';
$mode = 1;
$norefer = "1";
$pattern = "*.qiniu.com";

list($Info, $err) = $bucketManager->putReferAntiLeech($bucket, $mode, $norefer, $pattern);
if ($err) {
    print_r($err);
} else {
    print_r($Info);
}
