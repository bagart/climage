#!/usr/bin/php
<?php

if (php_sapi_name() != 'cli') {
    throw new \Exception('This application must be run on the command line.');
}
$loader = require_once __DIR__ . '/../../vendor/autoload.php';
$files = (new \Climage\Module\Connection\CloudGoogle())->getFileList();
/**
 * @var $files Google_Service_Drive_DriveFile[]
 */
for ($i = 0; $i < 10; ++$i) {
    echo $files[$i]->getTitle(), "\n";
}