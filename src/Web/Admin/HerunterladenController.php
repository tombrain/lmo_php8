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
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Ligen herunterladen oder per E-Mail schicken (todo=download): je eine Liste der Ligen im
 * Ligenordner, die Links fuehren zu lmo-admindownload.php bzw. lmo-adminmimesend.php (Nummer der
 * Liga in der sortierten Liste ab 1, -1 fuer alle). Hilfsadmins sehen nur ihre Ligen
 * (lmouserfile) und nicht "alle". Bis 4.5 lmo-admindown.php mit lmo-admindowndir.php und
 * lmo-adminmimedir.php.
 */
final class HerunterladenController implements Controller
{
    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->anfrage->text('todo') == 'download' && $kontext->sitzung->zahl('lmouserok') > 0;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $text = $kontext->text;
        $sitzung = $kontext->sitzung;
        $hauptadmin = $sitzung->zahl('lmouserok') == 2;
        $eigene = explode(',', $sitzung->text('lmouserfile'));
        $ordner = $kontext->konfiguration->ligenordner();
        $dateien = [];
        foreach (scandir($kontext->pfade->lmo . '/' . $ordner) ?: [] as $datei) {
            if (strtolower(substr($datei, -4)) == '.l98') {
                $dateien[] = $datei;
            }
        }
        sort($dateien);
        $ligen = [];
        foreach ($dateien as $k => $datei) {
            if ($hauptadmin || in_array(substr($datei, 0, -4), $eigene, true)) {
                $ligen[] = ['nr' => $k + 1, 'name' => LigaDatei::liesAbschnitt($kontext->pfade->lmo . '/' . $ordner . $datei, 'Options')['Name'] ?? ''];
            }
        }

        return new Seite(Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_herunterladen.html.twig', [
            'text' => $text,
            'download' => $kontext->pfade->adresse . '/lmo-admindownload.php?action=admin&amp;todo=download&amp;down=',
            'mail' => $kontext->pfade->adresse . '/lmo-adminmimesend.php?action=admin&amp;todo=email&amp;down=',
            'adminmail' => $kontext->konfiguration->wert('aadr'),
            // wie bis 4.5 heissen Ligen ohne Namen in den Listen verschieden (Text 223 bzw. 507)
            'ligenDownload' => self::mitNamen($ligen, $text[223] ?? ''),
            'ligenMail' => self::mitNamen($ligen, $text[507] ?? ''),
            'alle' => $hauptadmin && count($ligen) > 1,
        ]));
    }

    /**
     * Ligen ohne Namen heissen "<Text> 1", "<Text> 2" ...
     *
     * @param list<array{nr: int, name: string}> $ligen
     * @return list<array{nr: int, name: string}>
     */
    private static function mitNamen(array $ligen, string $ohneNamen): array
    {
        $j = 0;
        foreach ($ligen as $i => $liga) {
            if ($liga['name'] == '') {
                $ligen[$i]['name'] = $ohneNamen . ' ' . ++$j;
            }
        }

        return $ligen;
    }
}
