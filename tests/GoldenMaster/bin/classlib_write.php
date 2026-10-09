<?php
/**
 * Kindprozess: legt Ligen komplett ueber die classlib an und speichert sie mit liga::writeFile(),
 * so wie LMO das selbst tut (lmo-rueckrunde.php, lmo-adminrounds.php) - mit init.php und
 * updateAddons(). Alle Ligen in einem Prozess, wie mehrere writeFile()-Aufrufe in einem Request.
 *
 * Aufruf: php classlib_write.php <Instanzpfad> <Praefix> <Fixture.json> [<Fixture.json> ...]
 * Gespeichert wird unter ligen/<Praefix><Liga-ID>.l98. Ausgabe: JSON Liga-ID => true/false.
 */
$gm_instance = $argv[1];
$gm_prefix = $argv[2];
$gm_files = array_slice($argv, 3);
chdir($gm_instance);
if (getenv('GOLDEN_COVERAGE_DIR')) {
    require __DIR__ . '/coverage_prepend.php';
}

$_GET = [];
$_POST = [];
$_COOKIE = [];
$_REQUEST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'lmo.test';
$_SERVER['SCRIPT_NAME'] = '/lmo/lmoadmin.php';
$_SERVER['PHP_SELF'] = '/lmo/lmoadmin.php';
$_SERVER['SCRIPT_FILENAME'] = $gm_instance . '/lmoadmin.php';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require $gm_instance . '/init.php';
// nur diese eine Klasse, ohne den Composer-Autoloader des Projekts (die Instanz hat ihren eigenen)
require_once dirname(__DIR__, 2) . '/RealData/Support/RealLeague.php';

$gm_results = [];
foreach ($gm_files as $gm_file) {
    $gm_league = \Lmo\Tests\RealData\Support\RealLeague::load($gm_file);
    $gm_target = PATH_TO_LMO . '/' . $dirliga . $gm_prefix . $gm_league->id() . '.l98';
    ob_start();
    $gm_ok = $gm_league->buildLiga()->writeFile($gm_target);
    $gm_echo = ob_get_clean();
    $gm_results[$gm_league->id()] = $gm_ok && is_file($gm_target) && $gm_echo === '' ? true : trim(strip_tags($gm_echo));
}
echo json_encode($gm_results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
