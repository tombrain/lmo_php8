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

use Lmo\Liga\LigaDatei;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Eine Liga loeschen (todo=delete, nur der Hauptadmin): die Ligen des Ligenordners zur Auswahl;
 * mit del=1 wird die Datei dfile (relativ zum LMO) geloescht, aber nur eine Liga (.l98) im
 * Ligenordner oder einem seiner Unterordner (bis 4.5 jede Datei, auch ueber "../"). Bis 4.5
 * lmo-admindelete.php mit lmo-admindeldir.php.
 */
final class LoeschenController implements Controller
{
    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'delete' && $kontext->sitzung->zahl('lmouserok') == 2;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $anfrage = $kontext->anfrage;
        $text = $kontext->text;
        $meldung = '';
        if ($anfrage->text('del') == 1) {
            $datei = $anfrage->text('dfile');
            $meldung = self::loeschbar($kontext, $datei) && @unlink($kontext->pfade->lmo . '/' . $datei)
                ? Meldung::html($kontext->pfade->bilder, $datei . ' ' . ($text[297] ?? ''))
                : Meldung::html($kontext->pfade->bilder, $datei . ' ' . ($text[298] ?? ''), true);
        }

        // die Ligen im Ligenordner (ohne Unterordner); Ligen ohne Namen heissen "Liga 1", "Liga 2" ...
        $ordner = $kontext->konfiguration->ligenordner();
        $dateien = [];
        foreach (scandir($kontext->pfade->lmo . '/' . $ordner) ?: [] as $datei) {
            if (strtolower(substr($datei, -4)) == '.l98') {
                $dateien[] = $datei;
            }
        }
        sort($dateien);
        $ligen = [];
        $ohneNamen = 0;
        foreach ($dateien as $datei) {
            $name = LigaDatei::liesAbschnitt($kontext->pfade->lmo . '/' . $ordner . $datei, 'Options')['Name'] ?? '';
            if ($name == '') {
                $name = ($text[507] ?? '') . ' ' . ++$ohneNamen;
            }
            $ligen[] = ['pfad' => $ordner . $datei, 'name' => $name];
        }

        return new Seite($meldung . Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_loeschen.html.twig', [
            'text' => $text,
            'link' => $kontext->adresse . '?action=admin&amp;todo=delete&amp;del=1&amp;dfile=',
            'ligen' => $ligen,
        ]));
    }

    /** Ist die Datei (relativ zum LMO) eine Liga im Ligenordner oder einem seiner Unterordner? */
    private static function loeschbar(Kontext $kontext, string $datei): bool
    {
        $pfad = realpath($kontext->pfade->lmo . '/' . $datei);
        $ordner = realpath($kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner());

        return $pfad !== false && $ordner !== false && is_file($pfad) && str_starts_with($pfad, $ordner . DIRECTORY_SEPARATOR) && strtolower(substr($pfad, -4)) == '.l98';
    }
}
