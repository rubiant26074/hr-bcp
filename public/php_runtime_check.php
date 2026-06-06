<?php
$root = dirname(__DIR__);
require_once $root . '/config/auth.php';
require_login();
require_role(['Super Admin','HR']);

header('Content-Type: text/plain; charset=UTF-8');
echo 'SAPI=' . PHP_SAPI . PHP_EOL;
echo 'PHP_VERSION=' . PHP_VERSION . PHP_EOL;
echo 'LOADED_INI=' . php_ini_loaded_file() . PHP_EOL;
echo 'EXT_DIR=' . ini_get('extension_dir') . PHP_EOL;
echo 'ZIP_LOADED=' . (extension_loaded('zip') ? 'YES' : 'NO') . PHP_EOL;
echo 'ZIPARCHIVE_CLASS=' . (class_exists('ZipArchive') ? 'YES' : 'NO') . PHP_EOL;
