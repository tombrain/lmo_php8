<?php
declare(strict_types=1);

/*
 * Spiegelt vendor/ aus dem Docker-Volume ins vendor/ des Hosts (/host-vendor), damit die IDE
 * die Pakete kennt. Laeuft im Container: Composer ruft es nach jedem Autoload-Dump auf
 * (install, update, require, dump-autoload), von Hand docker/sync_vendor.ps1.
 *
 * Warum vendor/ im Volume liegt: ueber den Bind-Mount von Windows gelesen brauchte PagesTest
 * 11:17 min, mit vendor/ im Volume 4:40 min (11/2026). Jede Anfrage der Golden-Master-Tests
 * ist ein eigener PHP-Prozess und laedt Autoloader und Twig neu.
 *
 * Ohne /host-vendor (CI, Composer ausserhalb von Docker) tut es nichts.
 *
 * Verwendung (im Container):
 *   php docker/sync_vendor.php           # nur PHP-Dateien
 *   php docker/sync_vendor.php --force   # alle Dateien
 */

$hostVendor = '/host-vendor/';

if (!is_dir($hostVendor)) {
    exit(0);
}

$force = in_array('--force', $argv ?? [], true);
$vendor = dirname(__DIR__) . '/vendor/';

// --delete-excluded: auch Dateien loeschen, die der Filter ausschliesst (z. B. nach einem --force-Lauf)
// --prune-empty-dirs: keine Ordner anlegen, in denen nach dem Filter nichts uebrig bleibt
// --modify-window=1: Zeitstempel auf dem Windows-Dateisystem sind nicht nanosekundengenau
$args = ['rsync', '-rlt', '--delete', '--modify-window=1'];
if (!$force) {
    array_push($args, '--delete-excluded', '--prune-empty-dirs', '--include=*/', '--include=*.php', '--exclude=*');
}
array_push($args, $vendor, $hostVendor);

echo '[sync_vendor] Spiegle vendor/ (' . ($force ? 'alle Dateien' : 'PHP-Dateien') . ") auf den Host...\n";

passthru(implode(' ', array_map('escapeshellarg', $args)), $rc);

if ($rc !== 0) {
    fwrite(STDERR, "[sync_vendor] Fehler (Exit Code: $rc).\n");
    exit($rc);
}

echo "[sync_vendor] Fertig.\n";
