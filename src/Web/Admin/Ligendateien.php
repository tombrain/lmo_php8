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
 * Die Ligadateien (*.l98) eines Ordners, sortiert. Herunterladen und Liga per E-Mail nennen eine
 * Liga mit ihrer Nummer in dieser Liste (ab 1).
 */
final class Ligendateien
{
    /** @return list<string> die Dateinamen */
    public static function in(string $ordner): array
    {
        $dateien = [];
        foreach (scandir(rtrim($ordner, '/')) ?: [] as $datei) {
            if (strtolower(substr($datei, -4)) == '.l98') {
                $dateien[] = $datei;
            }
        }
        sort($dateien);

        return $dateien;
    }
}
