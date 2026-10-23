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

use Lmo\Web\Kontext;

/**
 * Erweiterungspunkt: Auswertung beim Speichern der Ergebnisse eines Spieltags in der Administration
 * (todo=edit), etwa die Tipps des Tippspiels. Noch nach alter Art: Die Administration bindet die
 * Dateien ueber eine Bruecke in lmo-adminmain.php im globalen Bereich ein (bis 4.5 in
 * lmo-adminedit.php), nachdem die Ergebnisse in $lmo_liga stehen und bevor die Liga gespeichert
 * wird; sie sehen dort $lmo_liga, $file, $liga (Dateiname ohne .l98), $st, $text und die
 * Einstellungen als Variablen. Die Bruecke ist eine Funktion: Variablen, die erst die Dateien anlegen
 * und die eine Funktion per global liest, muessen die Dateien selbst als global erklaeren.
 */
interface MitErgebnisAuswertung extends Addon
{
    /**
     * Die Dateien, die beim Speichern eingebunden werden; [] fuer keine (etwa wenn die Liga das
     * Addon nicht betrifft). $kontext->liga ist die bearbeitete Liga.
     *
     * @return list<string>
     */
    public function auswertungBeimSpeichern(Kontext $kontext): array;
}
