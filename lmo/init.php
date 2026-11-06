<?php
/** Liga Manager Online 4
  *
  * http://lmo.sourceforge.net/
  *
  * This program is free software; you can redistribute it and/or
  * modify it under the terms of the GNU General Public License as
  * published by the Free Software Foundation; either version 2 of
  * the License, or (at your option) any later version.
  *
  * This program is distributed in the hope that it will be useful,
  * but WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
  * General Public License for more details.
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  *
  *
  */

if (!isset($_SESSION)) {
    // no session has been started yet
    @ini_set('session.use_trans_sid', '1');
    @ini_set('arg_separator.output', '&amp;');
}

if (session_id() == '') session_start();
// Pfad/URL nur noch optional fest vorgegeben, sonst automatisch ermittelt (lmo-setup.php)
require_once(__DIR__ . '/lmo-setup.php');
if (is_file(__DIR__ . '/config/init-parameters.php')) {
    require(__DIR__ . '/config/init-parameters.php');
}
if (empty($lmo_dateipfad)) $lmo_dateipfad = __DIR__;
if (empty($lmo_url)) $lmo_url = lmo_detect_url($lmo_dateipfad);
if (($lmo_setup_error = lmo_setup_files($lmo_dateipfad)) !== '') {
    die('LMO: ' . htmlspecialchars($lmo_setup_error));
}

if (isset($_GET['debug']) || isset($_SESSION['debug'])) {
    $_SESSION['debug'] = true;
    @error_reporting(E_ALL);
    @ini_set('display_errors', '1');
}
$_SERVER['QUERY_STRING'] = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';

//Path-Contants
if (!defined('PATH_TO_LMO')) define('PATH_TO_LMO', $lmo_dateipfad);
if (!defined('PATH_TO_ADDONDIR')) define('PATH_TO_ADDONDIR', PATH_TO_LMO . '/addon');
if (!defined('PATH_TO_TEMPLATEDIR'))define('PATH_TO_TEMPLATEDIR', PATH_TO_LMO . '/template');
if (!defined('PATH_TO_IMGDIR')) define('PATH_TO_IMGDIR', PATH_TO_LMO . '/img');
if (!defined('PATH_TO_LANGDIR')) define('PATH_TO_LANGDIR', PATH_TO_LMO . '/lang');
if (!defined('PATH_TO_CONFIGDIR')) define('PATH_TO_CONFIGDIR', PATH_TO_LMO . '/config');
// Daten der Addons (daten/<addon>/, Lmo\Addon\Datenordner); bleiben bei Updates wie config/ und ligen/
if (!defined('PATH_TO_DATADIR')) define('PATH_TO_DATADIR', PATH_TO_LMO . '/daten');
if (!defined('PATH_TO_JSDIR')) define('PATH_TO_JSDIR', PATH_TO_LMO . '/js');

if (!defined('URL_TO_LMO')) define('URL_TO_LMO', $lmo_url);
if (!defined('URL_TO_ADDONDIR')) define('URL_TO_ADDONDIR', URL_TO_LMO . '/addon');
if (!defined('URL_TO_TEMPLATEDIR')) define('URL_TO_TEMPLATEDIR', URL_TO_LMO . '/template');
if (!defined('URL_TO_IMGDIR')) define('URL_TO_IMGDIR', URL_TO_LMO . '/img');
if (!defined('URL_TO_LANGDIR')) define('URL_TO_LANGDIR', URL_TO_LMO . '/lang');
if (!defined('URL_TO_CONFIGDIR')) define('URL_TO_CONFIGDIR', URL_TO_LMO . '/config');
if (!defined('URL_TO_JSDIR')) define('URL_TO_JSDIR', URL_TO_LMO . '/js');

// Check Path
if (!file_exists(PATH_TO_LMO . '/init.php')) {
    echo 'Invalid Path to LMO: ' . PATH_TO_LMO . ' - please reinstall or correct manually.';
    exit();
}

// Composer-Autoloader: pear/html_template_it und LMO_Template (classmap); nennt auch die installierte Version.
// lmo-install legt vendor/autoload.php als Verweis an; im Repo selbst liegt vendor/ neben lmo/.
require_once(is_file(PATH_TO_LMO . '/vendor/autoload.php') ? PATH_TO_LMO . '/vendor/autoload.php' : dirname(PATH_TO_LMO) . '/vendor/autoload.php');
// Klassen der Addons aus addon/<addon>/src (Lmo\Addon\Autoloader)
\Lmo\Addon\Autoloader::registriere(PATH_TO_ADDONDIR);

// Konfiguration (Lmo\Web\Start; bis 4.5 lmo-cfgload.php und lmo-updateoptions.php): als globale
// Variablen fuer den alten Code, die Classlib und eingebundene Seiten. Eine Seite, die lmo.php
// einbindet, kann $cfgarray vorher setzen; die Datei ergaenzt, was fehlt.
$cfgfile = PATH_TO_CONFIGDIR . '/cfg.txt';
if (!isset($cfgarray)) {
    $cfgarray = array();
}
$main_cfgarray = \Lmo\Web\Start::hauptkonfiguration($cfgfile);
if ($main_cfgarray === null) {
    // zweisprachig ohne Sprachtexte: die werden erst nach der Konfiguration geladen
    die('LMO: config/cfg.txt nicht gefunden. / config/cfg.txt not found.');
}
$cfgarray += $main_cfgarray;
extract($cfgarray);
$timezone = \Lmo\Web\Start::zeitzone($timezone ?? null);
@ini_set('date.timezone', $timezone);
date_default_timezone_set($timezone);
// Optionen, die aeltere Konfigurationen nicht kennen (ab beta3)
if (!isset($lmo_fieber_color1)) {
    $cfgarray['lmo_fieber_color1'] = '#00f';
    $lmo_fieber_color1 = '#00f';
}
if (!isset($lmo_fieber_color2)) {
    $cfgarray['lmo_fieber_color2'] = '#f00';
    $lmo_fieber_color2 = '#f00';
}
if (!isset($cfgarray['timezone'])) {
    $cfgarray['timezone'] = $timezone;
}
unset($cfgarray['einhinrueck']);
// die Addons: $cfgarray[<addon>] und Variablen mit dem Namen des Addons davor ($tipp_dirtipp ...)
foreach (\Lmo\Web\Start::addonKonfigurationen(PATH_TO_CONFIGDIR) as $lmo_addon => $lmo_addon_werte) {
    $cfgarray[$lmo_addon] = $lmo_addon_werte;
    extract($lmo_addon_werte, EXTR_PREFIX_ALL, $lmo_addon);
}
unset($lmo_addon, $lmo_addon_werte);

// Admin session keys: always ensure defaults exist, regardless of entry
// point (public frontend, admin area, tipp addon, ...), so downstream
// code can safely read $_SESSION['lmouserok'] etc. without isset() checks.
if (!isset($_SESSION['lmouserok'])) {
    $_SESSION['lmouserok'] = 0;
}
if (!isset($_SESSION['lmouserokerweitert'])) {
    $_SESSION['lmouserokerweitert'] = 0;
}
if (!isset($_SESSION['lmousername'])) {
    $_SESSION['lmousername'] = '';
}
if (!isset($_SESSION['lmouserpass'])) {
    $_SESSION['lmouserpass'] = '';
}
if (!isset($_SESSION['lmouserfile'])) {
    $_SESSION['lmouserfile'] = '';
}

// Sicherheits-Fix: Bereinigung des Parameters "subdir" (Unterverzeichnis der Ligadateien). Das frühere str_replace(array('../','./'), ...) war
// umgehbar: z.B. wurde aus "...././/" wieder "../" (Verzeichniswechsel nach oben). Jetzt werden alle Pfadteile einzeln geprüft: leere Teile,
// "." und ".." fallen weg, Backslashes und Nullbytes ebenso. Ergebnis ist immer '' oder ein relativer Pfad mit abschliessendem "/".
if (!function_exists('lmo_clean_subdir')) {
    function lmo_clean_subdir($subdir)
    {
        if (!is_string($subdir)) {
            return '';
        }
        $subdir = str_replace(array("\0", '\\'), array('', '/'), $subdir);
        $parts = array();
        foreach (explode('/', $subdir) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                continue;
            }
            $parts[] = $part;
        }
        return $parts ? implode('/', $parts) . '/' : '';
    }
}

//Language
if (isset($_GET['lmouserlang'])){
    $_SESSION['lmouserlang'] = $_GET['lmouserlang'];
}
if (isset($_POST['lmouserlang'])){
    $_SESSION['lmouserlang'] = $_POST['lmouserlang'];
}
if (isset($_SESSION['lmouserlang'])){
    $lmouserlang = $_SESSION['lmouserlang'];
} else {
    $lmouserlang = $deflang;
    $_SESSION['lmouserlang'] = $deflang;
}


// Sprachtexte (Lmo\Web\Sprachtexte; bis 4.5 lmo-langload.php): $text mit den Kerntexten unter ihrer
// Nummer und denen von lang/<ordner> und der Addons unter ihrem Namen. Nur bekannte Sprachen: beide
// kommen auch aus der Anfrage und werden in Dateinamen verwendet.
$languages = \Lmo\Web\Start::SPRACHEN;
[$deflang, $lmo_besucher, $lmo_ersetzt] = \Lmo\Web\Start::sprachen($deflang ?? null, $lmouserlang ?? null);
if ($lmo_besucher !== null) {
    $lmouserlang = $lmo_besucher;
}
if ($lmo_ersetzt) {
    $_SESSION['lmouserlang'] = $deflang;
}
unset($lmo_besucher, $lmo_ersetzt);
$text = \Lmo\Web\Sprachtexte::lies(PATH_TO_LANGDIR, $deflang, $lmouserlang ?? null, PATH_TO_ADDONDIR)->alsAltesArray();
@setlocale(LC_TIME, $languages[$deflang][0]);
if (isset($lmouserlang) && $lmouserlang != $deflang) {
    setlocale(LC_TIME, $languages[$lmouserlang][0]);
}
setlocale(LC_NUMERIC, 'en_EN');  // Wichtig: Fuer Arithmetik immer englische Trennzeichen
// die Bezeichnungen fuer Punkte und Tore, bevor eine Liga eigene setzt
$orgpkt = $text[37];
$orgtor = $text[38];
// Functions
require_once(PATH_TO_LMO . '/lmo-functions.php');
// installierte Version (bis 4.5 aus addon/classlib/ini.php); eigene Skripte, die die veraltete
// Classlib noch brauchen, binden addon/classlib/ini.php selbst ein
if (!defined('LMO_VERSION_NR')) {
    define('LMO_VERSION_NR', lmo_installed_version());
}
if (!defined('LMO_VERSION')) {
    define('LMO_VERSION', LMO_VERSION_NR);
}

// Remove Magic Quotes if necessary
magicQuotesRemove($_GET);
magicQuotesRemove($_POST);
magicQuotesRemove($_COOKIE);

// Workaround for register_globals TODO: fix that!!!
// Ohne Superglobale: bis 4.5 ueberschrieb ?_SESSION[lmouserok]=2 die Sitzung und meldete als
// Hauptadmin an. Nur neue Variablen (EXTR_SKIP): bis 4.5 ersetzten Parameter auch, was schon
// gesetzt war, etwa die Konfiguration (?dirliga=...) oder die Sprachtexte (?text[...]=...).
if (!function_exists('ini_get') || !ini_get('register_globals')) {
    // bei gleichem Namen gilt wie bisher Cookie vor Formular vor Adresse
    foreach (array($_COOKIE, $_POST, $_GET) as $lmo_anfragewerte) {
        @extract(array_filter($lmo_anfragewerte, static fn ($lmo_name): bool => is_string($lmo_name) && $lmo_name !== '' && $lmo_name[0] !== '_' && $lmo_name !== 'GLOBALS', ARRAY_FILTER_USE_KEY), EXTR_SKIP);
    }
    unset($lmo_anfragewerte);
}

?>
