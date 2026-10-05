<?php
/** Liga Manager Online 4
  *
  * Ersteinrichtung ohne Installer: Pfad und URL werden zur Laufzeit ermittelt,
  * die Standardkonfiguration wird aus config-default/ uebernommen (auch neue Optionen
  * nach einem Update) und das Admin-Passwort beim ersten Aufruf von lmoadmin.php festgelegt.
  * Einen Installer (frueher install/, inkl. FTP) gibt es nicht mehr.
  * config/init-parameters.php ist nur noch noetig, um Pfad oder URL fest vorzugeben.
  *
  * This program is free software; you can redistribute it and/or
  * modify it under the terms of the GNU General Public License as
  * published by the Free Software Foundation; either version 2 of
  * the License, or (at your option) any later version.
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  *
  */

/**
 * Absolute URL des LMO-Ordners ('' wenn sie sich nicht ermitteln laesst, z. B. auf der Kommandozeile).
 */
function lmo_detect_url(string $lmoDir): string
{
    if (empty($_SERVER['HTTP_HOST'])) {
        return '';
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || ($_SERVER['SERVER_PORT'] ?? '') == 443;
    $base = ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $lmoDir = lmo_normalize_path(realpath($lmoDir) ?: $lmoDir);

    // Aufgerufenes Skript liegt im LMO-Ordner (lmo.php, lmoadmin.php, addon/...):
    // Unterordner des Skripts vom URL-Pfad abziehen. Funktioniert auch mit Alias/Unterverzeichnis.
    $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($script !== false && $scriptName !== '') {
        $scriptDir = lmo_normalize_path(dirname($script));
        if ($scriptDir === $lmoDir || str_starts_with($scriptDir, $lmoDir . '/')) {
            $sub = substr($scriptDir, strlen($lmoDir));
            $urlDir = rtrim(lmo_normalize_path(dirname($scriptName)), '/');
            if ($sub === '' || str_ends_with($urlDir, $sub)) {
                return $base . substr($urlDir, 0, strlen($urlDir) - strlen($sub));
            }
        }
    }

    // LMO wird in eine andere Seite eingebunden: Lage relativ zur DOCUMENT_ROOT
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($docRoot !== false) {
        $docRoot = rtrim(lmo_normalize_path($docRoot), '/');
        if (str_starts_with($lmoDir . '/', $docRoot . '/')) {
            return $base . substr($lmoDir, strlen($docRoot));
        }
    }
    return '';
}

function lmo_normalize_path(string $path): string
{
    return str_replace('\\', '/', $path);
}

/**
 * Ersteinrichtung und Updates der Konfiguration aus config-default/:
 * - fehlende Dateien werden kopiert (Erstaufruf)
 * - fehlende Schluessel werden an vorhandene cfg.txt angehaengt (Update mit neuen Optionen);
 *   vorhandene Werte bleiben unveraendert
 * Laeuft nur, wenn sich config-default/ seit dem letzten Abgleich geaendert hat
 * (Marker config/.defaults-stamp). Das Admin-Konto entsteht beim ersten Aufruf von lmoadmin.php.
 *
 * @return string Fehlermeldung oder '' wenn alles bereit ist
 */
function lmo_setup_files(string $lmoDir): string
{
    // Meldungen hier zweisprachig: Vor der Ersteinrichtung gibt es noch keine Sprache (cfg.txt fehlt).
    $source = $lmoDir . '/config-default';
    $config = $lmoDir . '/config';
    $installed = is_file($config . '/cfg.txt');
    if (!is_dir($source)) {
        return $installed ? '' : 'config/cfg.txt fehlt und config-default/ ist nicht vorhanden. / '
            . 'config/cfg.txt is missing and config-default/ does not exist.';
    }
    $stamp = lmo_defaults_stamp($source);
    if ($installed && @file_get_contents($config . '/.defaults-stamp') === $stamp) {
        return '';
    }
    if (!is_writable($config)) {
        // Ein Update ohne Schreibrechte laeuft mit der alten Konfiguration weiter.
        return $installed ? '' : 'Der Ordner config/ ist nicht beschreibbar. Bitte Schreibrechte setzen '
            . '(z. B. mit dem FTP-Programm auf 775 bzw. 777). / '
            . 'The folder config/ is not writable. Please set write permissions (e.g. 775 or 777 with your FTP program).';
    }

    lmo_sync_defaults($source, $config);
    foreach (['ligen/archiv', 'output'] as $dir) {
        if (!is_dir($lmoDir . '/' . $dir)) {
            @mkdir($lmoDir . '/' . $dir, 0777, true);
        }
    }
    if (!is_file($lmoDir . '/addon/tipp/lmo-tippauth.txt')) {
        @file_put_contents($lmoDir . '/addon/tipp/lmo-tippauth.txt', '');
    }
    if (!is_file($config . '/cfg.txt')) {
        return 'config/cfg.txt konnte nicht angelegt werden. / config/cfg.txt could not be created.';
    }
    @file_put_contents($config . '/.defaults-stamp', $stamp);
    return '';
}

/** Kennung des Stands von config-default/ (Dateien, Groesse, Aenderungszeit). */
function lmo_defaults_stamp(string $source): string
{
    $parts = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $parts[] = substr($file->getPathname(), strlen($source)) . ':' . $file->getSize() . ':' . $file->getMTime();
    }
    sort($parts);
    return md5(implode('|', $parts));
}

function lmo_sync_defaults(string $from, string $to): void
{
    if (!is_dir($to)) {
        mkdir($to, 0777, true);
    }
    foreach (scandir($from) as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        if (is_dir($from . '/' . $name)) {
            lmo_sync_defaults($from . '/' . $name, $to . '/' . $name);
        } elseif (!file_exists($to . '/' . $name)) {
            copy($from . '/' . $name, $to . '/' . $name);
        } elseif ($name === 'cfg.txt') {
            lmo_merge_missing_keys($from . '/' . $name, $to . '/' . $name);
        }
    }
}

/** Haengt Schluessel aus $default an, die in $target fehlen (Zeile wie in der Vorlage). */
function lmo_merge_missing_keys(string $default, string $target): void
{
    $pattern = '/^\s*([^=;#\[\s]+)\s*=/';
    $have = [];
    foreach (file($target, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match($pattern, $line, $m)) {
            $have[$m[1]] = true;
        }
    }
    $add = '';
    foreach (file($default, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match($pattern, $line, $m) && !isset($have[$m[1]])) {
            $add .= rtrim($line, "\r") . "\n";
            $have[$m[1]] = true;
        }
    }
    if ($add === '' || !is_writable($target)) {
        return;
    }
    $current = (string)file_get_contents($target);
    if ($current !== '' && substr($current, -1) !== "\n") {
        $add = "\n" . $add;
    }
    file_put_contents($target, $add, FILE_APPEND | LOCK_EX);
}

/** Gibt es schon ein Admin-Konto? Sonst zeigt lmoadmin.php die Ersteinrichtung. */
function lmo_has_admin(): bool
{
    return is_file(PATH_TO_CONFIGDIR . '/lmo-auth.php');
}

/**
 * Legt das erste Admin-Konto an (Rang 2) und meldet es direkt an.
 *
 * @return int 0 bei Erfolg, sonst Nummer der Fehlermeldung in den Sprachdateien ($text)
 */
function lmo_create_admin(string $user, string $pass, string $pass2): int
{
    if (lmo_has_admin()) {
        return 5022;
    }
    if (!preg_match('/^[A-Za-z0-9_.@-]{3,40}$/', $user)) {
        return 5018;
    }
    if (strlen($pass) < 8) {
        return 5019;
    }
    if (str_contains($pass, '|') || str_contains($pass, "\n")) {
        return 5020;
    }
    if ($pass !== $pass2) {
        return 5021;
    }
    $hash = password_hash($pass, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    $content = "<?php exit(); ?>\n" . $user . '|' . $hash . "|2|||\n";
    // 'x': schlaegt fehl, falls zwei Anfragen gleichzeitig das erste Konto anlegen wollen
    $handle = @fopen(PATH_TO_CONFIGDIR . '/lmo-auth.php', 'x');
    if ($handle === false) {
        return 5023;
    }
    fwrite($handle, $content);
    fclose($handle);

    session_regenerate_id(true);
    $_SESSION['lmousername'] = $user;
    $_SESSION['lmouserpass'] = '';
    $_SESSION['lmouserok'] = 2;
    return 0;
}
