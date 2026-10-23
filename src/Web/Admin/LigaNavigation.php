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

use Lmo\Addon\Addons;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Kontext;
use Lmo\Web\Vorlagen;

/**
 * Die Navigation beim Bearbeiten einer Liga: Ergebnisse, die Punkte der Addons (z. B.
 * Spielerstatistik), Einstellungen, Tabellen (nur mit Handicap), Mannschaften (Hauptadmin und
 * erweiterte Hilfsadmins) und Runden. Die gezeigte Seite steht ohne Link. Bis 4.5
 * lmo-adminsubnavi.php; das bindet heute nur noch diese Klasse ein.
 */
final class LigaNavigation
{
    /**
     * @param Addons $addons die Addons der Administration (mit denen nach alter Art)
     * @param Liga $liga die Liga im geladenen oder gerade gespeicherten Stand
     * @param string $todo gewaehlte Aktion (edit, tabs)
     * @param string $st gewaehlter Spieltag oder Seite (-1 Einstellungen, -2 Mannschaften, -10 Runden, Addons eigene)
     * @param string $addr Adresse der Bearbeitung bis "st=" (todo=edit mit der Datei)
     * @param string $addb Adresse der Tabellen bis "st=" (todo=tabs mit der Datei)
     */
    public static function html(Kontext $kontext, Addons $addons, Liga $liga, string $todo, string $st, string $addr, string $addb): string
    {
        $sitzung = $kontext->sitzung;
        $einstellungen = $liga->einstellungen();

        // die Vergleiche lose wie bisher ($st kommt als Text aus der Anfrage)
        return Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_liganavigation.html.twig', [
            'text' => $kontext->text,
            'addr' => $addr,
            'addb' => $addb,
            'ergebnisseVerlinkt' => $st < 0 || $todo != 'edit',
            'addonPunkte' => $addons->ligaMenue($kontext),
            'einstellungenVerlinkt' => $st != -1,
            'tabellen' => $einstellungen->mitHandicap(),
            'tabellenVerlinkt' => $todo != 'tabs',
            'aktuellerSpieltag' => $einstellungen->aktuellerSpieltag(),
            'mannschaften' => $sitzung->zahl('lmouserok') == 2 || $sitzung->zahl('lmouserokerweitert') == 1,
            'mannschaftenVerlinkt' => $st != -2,
            'rundenVerlinkt' => $st != -10,
        ]);
    }
}
