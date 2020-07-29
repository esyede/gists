<?php

define('DS', DIRECTORY_SEPARATOR);

require 'gists.php';

$__config = require 'config.php';
$paste = new Esyede\Gists(
    $__config['app_name'],
    $__config['app_key'],
    $__config['app_language'],
    $__config['app_timezone']
);

$paste->sweep();

if (isset($_GET['p'])) {
    $paste->show($_GET['p']);
} elseif (isset($_POST['d']) && isset($_POST['p'])) {
    $paste->make($_POST['d'], isset($_POST['prettify']), isset($_POST['wrap']), $_POST['p']);
} else {
    $paste->prompt();
}

unset($__config);
exit;
