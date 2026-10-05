<?php
/**
 * Kindprozess: allgemeine HTTP-Anfrage (GET oder POST) gegen einen beliebigen Einstiegspunkt
 * der Testinstanz, optional innerhalb einer bestehenden Sitzung.
 * Aufruf: php http.php <Instanzpfad> <Spezifikationsdatei (JSON)>
 *
 * Spezifikation: { script, query, method, post, sid, sidOut }
 *   sid     = Sitzungs-ID einer frueheren Anfrage (leer = neue Sitzung)
 *   sidOut  = Datei, in die am Ende die Sitzungs-ID geschrieben wird
 *
 * Eigene Variablen tragen das Praefix $gm_, damit sie nicht mit dem Altcode kollidieren.
 */
$gm_instance = $argv[1];
$gm_spec = json_decode((string)file_get_contents($argv[2]), true);
chdir($gm_instance);
if (getenv('GOLDEN_COVERAGE_DIR')) {
    require __DIR__ . '/coverage_prepend.php';
}

$gm_script = $gm_spec['script'];
$gm_query = isset($gm_spec['query']) ? $gm_spec['query'] : '';

$_GET = [];
parse_str($gm_query, $_GET);
$_POST = isset($gm_spec['post']) ? $gm_spec['post'] : [];
if (isset($gm_spec['postRaw'])) {
    parse_str($gm_spec['postRaw'], $_POST); // wie PHP es beim Browser-Formular tut (inkl. name[] zu Arrays)
}
$_COOKIE = [];
$_FILES = [];
$_REQUEST = array_merge($_GET, $_POST);
$_SERVER['REQUEST_METHOD'] = isset($gm_spec['method']) ? $gm_spec['method'] : 'GET';
$_SERVER['HTTP_HOST'] = 'lmo.test';
$_SERVER['SERVER_NAME'] = 'lmo.test';
$_SERVER['SCRIPT_NAME'] = '/lmo/' . $gm_script;
$_SERVER['PHP_SELF'] = '/lmo/' . $gm_script;
$_SERVER['SCRIPT_FILENAME'] = $gm_instance . '/' . $gm_script;
$_SERVER['REQUEST_URI'] = '/lmo/' . $gm_script . ($gm_query !== '' ? '?' . $gm_query : '');
$_SERVER['QUERY_STRING'] = $gm_query;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'golden-master';
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de';

if (!empty($gm_spec['sid'])) {
    // Bestehende Sitzung fortsetzen. Die beiden Einstellungen setzt init.php sonst selbst,
    // bevor es die Sitzung startet; hier wird es vorweggenommen, weil die Sitzung schon laeuft.
    @ini_set('session.use_trans_sid', '1');
    @ini_set('arg_separator.output', '&amp;');
    session_id($gm_spec['sid']);
    session_start();
}

register_shutdown_function(static function () use ($gm_spec) {
    file_put_contents(
        $gm_spec['sidOut'],
        session_status() === PHP_SESSION_ACTIVE ? session_id() : ''
    );
});

require $gm_instance . '/' . $gm_script;
