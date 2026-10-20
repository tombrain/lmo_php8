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

namespace Lmo\Web\Seiten;

use Lmo\Web\Kontext;
use Lmo\Web\Vorlagen;

/**
 * Die Links zur Druckansicht unter der Seite (Teil Savehtml des Rahmens): Spieltag mit Tabelle
 * (<liga>-st.html) und Spielplan (<liga>-sp.html). Die Dateien schreibt das Speichern der Liga
 * (lmo-saveliga.php, classlib); hier nur die Links darauf.
 */
final class Druckansicht
{
    /**
     * Das HTML ('' ohne geladene Liga oder wenn die Druckansicht abgeschaltet ist, einsavehtml).
     *
     * @param bool $druckbar die Seite hat eine Druckansicht (Lmo\Web\Seite::$druckbar)
     */
    public static function html(Kontext $kontext, bool $druckbar): string
    {
        if ($kontext->liga === null || !$kontext->konfiguration->schalter('einsavehtml')) {
            return '';
        }
        $text = $kontext->text;
        $ordner = $kontext->konfiguration->ausgabeordner();
        $links = [];
        foreach (['-st.html' => [477, 478], '-sp.html' => [479, 480]] as $endung => [$titel, $name]) {
            $datei = $ordner . basename($kontext->liga->datei) . $endung;
            // im Pokal gibt es keine Druckansicht
            $links[] = $druckbar && !$kontext->liga->liga->istPokal() && file_exists($kontext->pfade->lmo . '/' . $datei)
                ? ['adresse' => $kontext->pfade->adresse . '/' . $datei, 'titel' => $text[$titel], 'name' => $text[$name]]
                : null;
        }

        return Vorlagen::zeige($kontext->pfade->vorlagen, 'druckansicht.html.twig', ['links' => $links]);
    }
}
