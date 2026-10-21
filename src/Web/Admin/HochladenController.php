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
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Eine Liga hochladen (todo=upload, nur der Hauptadmin): das Formular und, mit upl, die Datei in
 * den Ligenordner; gibt es den Namen schon, kommt "1_", "2_" ... davor. Bis 4.5 lmo-adminupload.php.
 */
final class HochladenController implements Controller
{
    /**
     * @param Closure(): (array{tmp: string, name: string}|null) $hochgeladen die hochgeladene Datei ($_FILES['userfile'] mit upl), sonst null
     * @param Closure(string, string): bool $verschiebe von der temporaeren Datei zum Ziel (move_uploaded_file)
     */
    public function __construct(private Closure $hochgeladen, private Closure $verschiebe)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'upload' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $text = $kontext->text;
        $bilder = $kontext->pfade->bilder;
        $meldung = '';
        $datei = ($this->hochgeladen)();
        if ($datei !== null) {
            if (substr($datei['name'], -4) != '.l98') {
                // wie bis 4.5 nur die Meldung, ohne Formular
                return new Seite(Meldung::html($bilder, $text[304] ?? '', true));
            }
            $ordner = $kontext->konfiguration->ligenordner();
            $ziel = $ordner . $datei['name'];
            for ($i = 1; file_exists($kontext->pfade->lmo . '/' . $ziel); $i++) {
                $ziel = $ordner . $i . '_' . $datei['name'];
            }
            if (($this->verschiebe)($datei['tmp'], $kontext->pfade->lmo . '/' . $ziel)) {
                $meldung = Meldung::html($bilder, ($text[303] ?? '') . ':<br>' . $ziel);
                @chmod($kontext->pfade->lmo . '/' . $ziel, 0644);
            } else {
                $meldung = Meldung::html($bilder, $text[304] ?? '', true);
            }
        }

        return new Seite($meldung . Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_hochladen.html.twig', [
            'text' => $text,
            'adresse' => $kontext->adresse,
        ]));
    }
}
