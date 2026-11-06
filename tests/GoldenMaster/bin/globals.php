<?php
/**
 * Kindprozess: bindet init.php ein wie eine Seite des LMO und gibt aus, was danach da ist: die
 * globalen Variablen (Sprachtexte als Anzahl und Pruefsumme), die Konstanten des LMO, Zeitzone,
 * Locale, die Sitzung und die Funktionen, die init.php anlegt. Ausgabe: JSON auf stdout.
 * Aufruf: php globals.php <Instanzpfad> <Abfrage wie a=1&b=2>
 *
 * Bewusst im globalen Bereich wie das Original; eigene Variablen tragen das Praefix $gm_.
 */
$gm_instance = $argv[1];
parse_str($argv[2] ?? '', $gm_query);
chdir($gm_instance);
if (getenv('GOLDEN_COVERAGE_DIR')) {
    require __DIR__ . '/coverage_prepend.php';
}

$_GET = $gm_query;
$_POST = [];
$_COOKIE = [];
$_REQUEST = $_GET;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'lmo.test';
$_SERVER['SCRIPT_NAME'] = '/lmo/lmo.php';
$_SERVER['PHP_SELF'] = '/lmo/lmo.php';
$_SERVER['SCRIPT_FILENAME'] = $gm_instance . '/lmo.php';
$_SERVER['QUERY_STRING'] = $argv[2] ?? '';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$gm_funktionen = get_defined_functions()['user'];

require $gm_instance . '/init.php';

$gm_variablen = [];
foreach (get_defined_vars() as $gm_name => $gm_wert) {
    if (in_array($gm_name, ['GLOBALS', '_GET', '_POST', '_COOKIE', '_FILES', '_SERVER', '_REQUEST', '_ENV', '_SESSION', 'argv', 'argc', '__composer_autoload_files'], true) || strpos($gm_name, 'gm_') === 0) {
        continue;
    }
    $gm_variablen[$gm_name] = $gm_name === 'text' ? ['anzahl' => count($gm_wert, COUNT_RECURSIVE), 'md5' => md5(serialize($gm_wert)), '37' => $gm_wert[37] ?? null, '1' => $gm_wert[1] ?? null, 'date0' => $gm_wert['date'][0] ?? null] : $gm_wert;
}
ksort($gm_variablen);
$gm_konstanten = array_filter(get_defined_constants(true)['user'] ?? [], static fn (string $n): bool => strpos($n, 'PATH_TO_') === 0 || strpos($n, 'URL_TO_') === 0 || strpos($n, 'LMO_') === 0, ARRAY_FILTER_USE_KEY);
ksort($gm_konstanten);
$gm_neu = array_values(array_diff(get_defined_functions()['user'], $gm_funktionen));
sort($gm_neu);

echo str_replace($gm_instance, '{LMO_PATH}', (string) json_encode([
    'variablen' => $gm_variablen,
    'konstanten' => $gm_konstanten,
    'zeitzone' => date_default_timezone_get(),
    'locale_time' => setlocale(LC_TIME, '0'),
    'locale_numeric' => setlocale(LC_NUMERIC, '0'),
    'sitzung' => $_SESSION,
    'funktionen' => $gm_neu,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR));
