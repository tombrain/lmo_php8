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
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Rohdaten\Partie;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Meldung;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Anzahl der Spieltage und der Spiele je Spieltag einer Liga aendern (todo=edit&st=-3, Hauptadmin
 * und erweiterte Hilfsadmins). Mit save=1 wird gespeichert; was hinter der letzten Partie oder dem
 * letzten Spieltag lag, beginnt dabei leer. Bis 4.5 lmo-adminanz.php.
 */
final class AnzahlController implements Controller
{
    /** @param Closure(Liga, string, string): array{string, Liga} $speichere speichert eine Liga wie lmo-saveliga.php (Rohdaten, Datei, gewaehlter Spieltag) */
    public function __construct(private Closure $speichere)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        $sitzung = $kontext->sitzung;

        return $kontext->anfrage->text('todo') == 'edit' && $kontext->anfrage->text('st') == -3
            && ($sitzung->zahl('lmouserok') == 2 || ($sitzung->zahl('lmouserok') == 1 && $sitzung->zahl('lmouserokerweitert') == 1));
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        // ohne Liga (fehlt oder fuer diesen Hilfsadmin nicht freigegeben) bleibt die Seite leer
        if ($geladen === null) {
            return new Seite('');
        }
        $anfrage = $kontext->anfrage;
        $liga = $geladen->liga;
        $meldung = '';
        if ($anfrage->text('save') == 1) {
            // Was bisher hinter der letzten Partie oder dem letzten Spieltag lag, beginnt leer
            $einstellungen = $liga->einstellungen();
            $spieltage = $einstellungen->spieltage();
            $partien = $einstellungen->partienJeSpieltag();
            for ($i = 1; $i <= 116; $i++) {
                for ($j = $i <= $spieltage ? $partien + 1 : 1; $j <= 40; $j++) {
                    $liga->spieltag($i)->partien[$j] = Partie::leer();
                }
            }
            $neu = trim($anfrage->text('xanzst'));
            $liga->setzeEinstellung('Rounds', $neu);
            $liga->setzeEinstellung('Matches', trim($anfrage->text('xanzsp')));
            // der aktuelle Spieltag liegt hoechstens auf dem letzten
            if ($einstellungen->aktuellerSpieltag() > $neu) {
                $liga->setzeEinstellung('Actual', $neu);
            }
            [$meldung, $liga] = ($this->speichere)($liga, $geladen->datei, $anfrage->text('st'));
        }
        $addr = $kontext->adresse . '?action=admin&amp;todo=edit&amp;file=' . $geladen->datei . '&amp;st=';
        $addb = $kontext->adresse . '?action=admin&amp;todo=tabs&amp;file=' . $geladen->datei . '&amp;st=';

        return new Seite($meldung . Vorlagen::zeige($kontext->pfade->vorlagen, 'admin_anzahl.html.twig', [
            'text' => $kontext->text,
            'adresse' => $kontext->adresse,
            'bilder' => $kontext->pfade->bilder,
            'navigation' => LigaNavigation::html($kontext, $kontext->addons, $liga, 'edit', $anfrage->text('st'), $addr, $addb),
            'warnung' => Meldung::html($kontext->pfade->bilder, $kontext->text[561] ?? '', true),
            'datei' => $geladen->datei,
            'st' => $anfrage->text('st'),
            'spieltage' => $liga->einstellungen()->wert('Rounds'),
            'spiele' => $liga->einstellungen()->wert('Matches'),
            'addr' => $addr,
        ]));
    }
}
