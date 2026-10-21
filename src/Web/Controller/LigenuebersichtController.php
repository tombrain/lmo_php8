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

namespace Lmo\Web\Controller;

use Closure;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Seiten\Ligenliste;
use Lmo\Web\Vorlagen;

/**
 * Die Ligenuebersicht (ohne gewaehlte Liga): die Ligen eines Ordners (Lmo\Web\Seiten\Ligenliste)
 * und darunter seine Unterordner mit ihrer Beschreibung (dir-descr.txt). Vorlage
 * ligenuebersicht.html.twig.
 *
 * Parameter der Anfrage:
 *  - subdir                          Unterordner des Ligenordners ("archiv/2024/")
 *  - liga_sort, liga_sort_direction  Sortierung der Liste (ohne: die der Konfiguration)
 */
final class LigenuebersichtController implements Controller
{
    /**
     * @param bool $angemeldet angemeldet (Admin oder Hilfsadmin): die Liste zeigt die Dateinamen
     * @param Closure(string): bool $darfLesen Ligadatei => darf der Besucher sie sehen? (check_hilfsadmin)
     */
    public function __construct(
        private bool $angemeldet,
        private Closure $darfLesen,
    ) {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->liga === null;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $k = $kontext->konfiguration;
        $anfrage = $kontext->anfrage;
        // kein Weg aus dem Ligenordner heraus
        $unterordner = str_replace(['../', './'], ['', ''], $anfrage->text('subdir'));
        $ligenordner = $kontext->pfade->lmo . '/' . $k->ligenordner();

        $liste = (new Ligenliste($kontext->text, $kontext->darstellung, $kontext->pfade->vorlagen, $kontext->pfade->bilder, $kontext->pfade->adresse, $this->darfLesen))->zeige(
            rtrim($ligenordner . $unterordner, '/') . '/',
            $unterordner,
            $kontext->adresse,
            $kontext->adresse . '?todo=&amp;file=',
            $this->angemeldet,
            $anfrage->text('liga_sort', $k->wert('liga_sort')),
            $anfrage->text('liga_sort_direction', $k->wert('liga_sort_direction')),
            $k->datumsformat()
        );

        // die Unterordner, natuerlich sortiert, mit ihrer Beschreibung
        $ordner = [];
        if ($k->schalter('archivlink')) {
            if ($unterordner !== '' && substr($unterordner, -1) != '/') {
                $unterordner .= '/';
            }
            $namen = [];
            foreach (is_dir($ligenordner . $unterordner) ? (scandir($ligenordner . $unterordner) ?: []) : [] as $name) {
                if ($name != '.' && $name != '..' && is_dir($ligenordner . $unterordner . $name)) {
                    $namen[] = $name;
                }
            }
            natcasesort($namen);
            foreach ($namen as $name) {
                $beschreibung = @file_get_contents($ligenordner . $unterordner . $name . '/dir-descr.txt');
                // die Beschreibung wie bisher mit htmlentities() (Umlaute als Entitaeten)
                $ordner[] = ['name' => $name, 'pfad' => $unterordner . $name . '/', 'beschreibung' => $beschreibung === false ? '' : htmlentities($beschreibung)];
            }
        }

        return new Seite(Vorlagen::zeige($kontext->pfade->vorlagen, 'ligenuebersicht.html.twig', [
            'text' => $kontext->text,
            'adresse' => $kontext->adresse,
            'liste' => $liste,
            'archiv' => $k->schalter('archivlink'),
            'ordner' => $ordner,
            // im Unterordner: zurueck zum Ordner darueber (wie bisher dirname(), also "./" fuer die oberste Ebene)
            'zurueck' => $k->schalter('archivlink') && str_contains($unterordner, '/') ? dirname($unterordner) . '/' : null,
        ]));
    }
}
