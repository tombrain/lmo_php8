<?php
/* Liga Manager Online 4
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

require_once(__DIR__ . '/../../init.php');

/**
 * Die Spielerstatistik zum Drucken (Lmo\Addon\Spieler\Druckansicht), direkt aufgerufen; init.php stellt bereit:
 *
 * @var array<string, mixed> $cfgarray Konfiguration der Installation
 * @var array<int|string, mixed> $text Sprachtexte
 */

$file = isset($_GET['file']) ? $_GET['file'] : exit;

$lmo_texte = \Lmo\Web\Sprachtexte::ausAltemText($text);
$lmo_konfiguration = new \Lmo\Web\Konfiguration($cfgarray);
echo \Lmo\Addon\Spieler\Druckansicht::html(
    __DIR__ . '/stats',
    (string)$file,
    \Lmo\Web\Anfrage::ausGlobalen(),
    \Lmo\Addon\Spieler\Einstellungen::lies(\Lmo\Addon\Spieler\Einstellungen::datei(__DIR__ . '/stats', (string)$file), $lmo_konfiguration->addon('spieler'), $lmo_texte->von('spieler')),
    $lmo_texte->kern,
    $lmo_texte->von('spieler'),
    URL_TO_IMGDIR,
    new \Lmo\Web\Darstellung(
        static fn (string $datei, string $name, string $attribute): string => '',
        null,
        static fn (string $name, string $attribute): string => (string)HTML_smallSpielerIcon($name, $attribute)
    )
);
