<?php
/**
 * Kindprozess: simuliert eine GET-Anfrage an lmo.php der Testinstanz.
 * Aufruf: php request.php <Instanzpfad> <Querystring>
 */
$instance = $argv[1];
$query = isset($argv[2]) ? $argv[2] : '';
chdir($instance);
if (getenv('GOLDEN_COVERAGE_DIR')) {
    require __DIR__ . '/coverage_prepend.php';
}

$_GET = [];
parse_str($query, $_GET);
$_POST = [];
$_COOKIE = [];
$_REQUEST = $_GET;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'lmo.test';
$_SERVER['SERVER_NAME'] = 'lmo.test';
$_SERVER['SCRIPT_NAME'] = '/lmo/lmo.php';
$_SERVER['PHP_SELF'] = '/lmo/lmo.php';
$_SERVER['SCRIPT_FILENAME'] = $instance . '/lmo.php';
$_SERVER['REQUEST_URI'] = '/lmo/lmo.php' . ($query !== '' ? '?' . $query : '');
$_SERVER['QUERY_STRING'] = $query;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'golden-master';
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de';

require $instance . '/lmo.php';
