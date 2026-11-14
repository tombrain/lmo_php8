<?php
/** Liga Manager Online 4
  *
  * http://lmo.sourceforge.net/
  *
  * Umgebaut fuer PHP 8 im Rahmen von lmo_php8 (https://github.com/henshingly/lmo_php8).
  *
  * SPDX-License-Identifier: GPL-2.0-or-later
  *
  * REMOVING OR CHANGING THE COPYRIGHT NOTICES IS NOT ALLOWED!
  */

declare(strict_types=1);

namespace Lmo\Addon;

/**
 * Der Datenordner eines Addons: daten/<name>/ im LMO-Ordner (PATH_TO_DATADIR). Dort legt ein Addon
 * ab, was beim Betrieb entsteht (Tipps, Statistiken ...). Der Ordner gehoert zur Installation, nicht
 * zum Paket: ein Update ersetzt den Ordner des Addons, der Datenordner bleibt.
 *
 * Bis 4.5 lagen solche Daten im Ordner des Addons; umziehen() verschiebt sie einmal hierher.
 */
final class Datenordner
{
    /**
     * Der Ordner, in dem das Addon seine Daten findet. Gibt es den neuen Ordner noch nicht, werden die
     * Eintraege vom alten Ort dorthin verschoben; scheitert das (etwa ohne Schreibrechte), bleibt
     * alles am alten Ort und der gilt weiter.
     *
     * @param string $neu der Datenordner (PATH_TO_DATADIR . '/<name>')
     * @param string $alt der bisherige Ort der Daten
     * @param list<string>|null $eintraege Dateien und Ordner in $alt, die umziehen; null: alles in $alt
     * @return string der Ordner, der gilt: $neu, oder $alt, wenn die Daten dort bleiben mussten
     */
    public static function umziehen(string $neu, string $alt, ?array $eintraege = null): string
    {
        if (is_dir($neu)) {
            return $neu;
        }
        $eintraege ??= is_dir($alt) ? array_values(array_diff(scandir($alt) ?: [], ['.', '..'])) : [];
        $vorhanden = [];
        foreach ($eintraege as $eintrag) {
            $eintrag = trim($eintrag, '/');
            if ($eintrag !== '' && file_exists($alt . '/' . $eintrag)) {
                $vorhanden[] = $eintrag;
            }
        }
        if (!@mkdir($neu, 0777, true) && !is_dir($neu)) {
            return $vorhanden !== [] ? $alt : $neu;
        }
        $verschoben = [];
        foreach ($vorhanden as $eintrag) {
            if (!@rename($alt . '/' . $eintrag, $neu . '/' . $eintrag)) {
                // alles zurueck: die Daten bleiben beisammen am alten Ort
                foreach ($verschoben as $zurueck) {
                    @rename($neu . '/' . $zurueck, $alt . '/' . $zurueck);
                }
                @rmdir($neu);

                return $alt;
            }
            $verschoben[] = $eintrag;
        }

        return $neu;
    }
}
