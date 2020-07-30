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

if (isset($_GET['p'])) {
    $paste->show($_GET['p']);
} elseif (isset($_GET['sweep'])) {
	if ($_GET['sweep'] !== $__config['app_key']) {
		exit('Unable to sweep: Invalid application key.');
	}

	ini_set('max_execution_time', 0);
	set_time_limit(0);
    $paste->sweep();
    exit('Sweep operation success.');
} elseif (isset($_POST['d']) && isset($_POST['p'])) {
    $paste->make($_POST['d'], isset($_POST['prettify']), isset($_POST['wrap']), $_POST['p']);
} else {
    $paste->prompt();
}

unset($__config);
exit;
