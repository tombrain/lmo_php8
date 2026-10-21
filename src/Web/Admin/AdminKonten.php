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

namespace Lmo\Web\Admin;

/**
 * Die Konten der Administration in config/lmo-auth.php: je Zeile "Name|Passwort|Rang|Ligen|Erweitert",
 * [0]=Name, [1]=Passwort (Hash), [2]=Rang (2 Admin, 1 Hilfsadmin), [3]=Ligen des Hilfsadmins
 * (Dateinamen ohne .l98, mit Komma getrennt), [4]=Erweiterter Hilfsadmin. Das erste Konto ist der
 * Hauptadmin. Bis 4.5 lmo-loadauth.php und lmo-saveauth.php.
 */
final class AdminKonten
{
    /**
     * Die Konten; null, wenn die Datei nicht lesbar ist.
     *
     * @return list<list<string>>|null
     */
    public static function lies(string $datei): ?array
    {
        $handle = is_file($datei) ? @fopen($datei, 'rb') : false;
        if ($handle === false) {
            return null;
        }
        $konten = [];
        while (($zeile = fgetcsv($handle, 10000, '|', '"', '\\')) !== false) {
            // die erste Zeile (die Sperre gegen den Direktaufruf) hat nur ein Feld
            if (count($zeile) > 1) {
                $konten[] = array_map(static fn (?string $feld): string => (string) $feld, $zeile);
            }
        }
        fclose($handle);

        return $konten;
    }

    /**
     * Schreibt die Konten; false, wenn die Datei nicht geschrieben werden konnte.
     *
     * @param list<list<string>> $konten
     */
    public static function schreibe(string $datei, array $konten): bool
    {
        $inhalt = "<?php exit(); ?>\n";
        foreach ($konten as $konto) {
            if (count($konto) > 1) {
                $inhalt .= implode('|', $konto) . "\n";
            }
        }

        return @file_put_contents($datei, $inhalt, LOCK_EX) !== false;
    }
}
