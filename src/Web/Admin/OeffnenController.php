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

use Closure;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Eine Liga oeffnen (todo=open): die Ligen eines Ordners (subdir) und seine Unterordner mit ihrer
 * Beschreibung (dir-descr.txt), dazu der Weg zurueck. Bis 4.5 lmo-adminopen.php mit lmo-dirlist.php.
 */
final class OeffnenController implements Controller
{
    /** @param Closure(string): bool $darfLesen Ligadatei => darf der Angemeldete sie sehen? (check_hilfsadmin) */
    public function __construct(private Closure $darfLesen)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'open' && $kontext->sitzung->zahl('lmouserok') > 0;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $unterordner = str_replace(['../', './'], '', $kontext->anfrage->text('subdir'));
        $ligen = StartseiteController::ligenliste($kontext, $this->darfLesen, 'open', $unterordner);

        $ordner = $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner() . $unterordner;
        $ordner .= substr($ordner, -1) != '/' ? '/' : '';
        $namen = [];
        foreach (is_dir($ordner) ? (scandir($ordner) ?: []) : [] as $name) {
            if ($name != '.' && $name != '..' && is_dir($ordner . $name)) {
                $namen[] = $name;
            }
        }
        natcasesort($namen);
        if ($unterordner != '' && substr($unterordner, -1) != '/') {
            $unterordner .= '/';
        }
        $ordnerliste = [];
        foreach ($namen as $name) {
            // die Beschreibung wie bis 4.5 mit htmlentities() (Umlaute als Entitaeten)
            $beschreibung = is_file($ordner . $name . '/dir-descr.txt') ? htmlentities((string) file_get_contents($ordner . $name . '/dir-descr.txt')) : '';
            $ordnerliste[] = ['name' => $name, 'pfad' => $unterordner . $name . '/', 'beschreibung' => $beschreibung];
        }

        return new Seite(Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_oeffnen.html.twig', [
            'text' => $kontext->text,
            'adda' => $kontext->adresse . '?action=admin&amp;todo=',
            'ligen' => $ligen,
            'ordner' => $ordnerliste,
            // wie bis 4.5 der Ordner darueber ueber dirname() ("./" fuer eine Ebene unter dem Ligenordner)
            'zurueck' => str_contains($unterordner, '/') ? dirname($unterordner) . '/' : null,
        ]));
    }
}
