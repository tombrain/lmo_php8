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

use DateTime;
use Lmo\Liga\Paarung;
use Lmo\Liga\Spiel;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Seiten\SpieltagsMenue;
use Lmo\Web\Seiten\SpielHinweis;
use Lmo\Web\Vorlagen;

/**
 * Die Ergebnisse einer Pokalrunde (action=results im Pokal): je Paarung ihre Spiele, der
 * Sieger der Paarung hervorgehoben. Vorlage pokal_ergebnisse.html.twig.
 *
 * In Playoffs wechselt das Heimrecht (Modus 2-2-1 und 2-2-1-1-1: Spiele 1, 2, 5 und 7 beim
 * Heimteam der Paarung; 2-3-2: Spiele 1, 2, 6 und 7; sonst jedes zweite Spiel); Spiele mit
 * gewechseltem Heimrecht stehen mit der Gastmannschaft der Paarung links.
 *
 * Parameter der Anfrage:
 *  - st  gewaehlte Runde (ohne: die aktuelle)
 *
 * Addons mit Zusatz je Spiel (Lmo\Addon\MitZusatzJeSpiel) stehen zwischen Spielbericht und Notiz.
 */
final class PokalErgebnisseController implements Controller
{
    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->liga !== null && $kontext->liga->liga->istPokal() && $kontext->action == 'results';
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        assert($geladen !== null);
        $liga = $geladen->liga;
        $e = $liga->einstellungen();
        // die Liga kann die Ergebnisse abschalten
        if (!$e->mitErgebnissen()) {
            return new Seite('');
        }
        $text = $kontext->text;
        $st = $kontext->anfrage->zahl('st', $e->aktuellerSpieltag());
        $runden = $e->spieltage();
        $platz3 = $e->mitSpielUmPlatz3() && $st == $runden;
        $rundenLink = $kontext->adresse . '?action=results&amp;file=' . $geladen->datei . '&amp;st=';

        $breite = 10;
        if ($e->mitVerlaengerung()) {
            $breite += 2;
        }
        if ($e->mitAnstosszeit()) {
            $breite += 1;
        }
        // Paarungen dieser Runde, gerechnet wie bisher (fuer die Trennzeilen zwischen ihnen)
        $anzahl = $e->mannschaften();
        for ($i = 0; $i < $st; $i++) {
            $anzahl = $anzahl / 2;
        }
        if ($platz3) {
            $anzahl = $anzahl + 1;
        }

        // Name der Runde: die letzten vier heissen Finale, Halbfinale usw.
        $vomEnde = $runden - $st;
        $titel = $vomEnde >= 0 && $vomEnde <= 3 ? $text[374 - $vomEnde] : $st . '. ' . $text[370];
        $tag = $liga->spieltage[$st] ?? null;
        if ($e->mitSpieltagsdatum() && $tag !== null) {
            if ($tag->ersterTagText() != '') {
                $titel .= ' ' . $text[3] . ' ' . $tag->ersterTagText();
            }
            if ($tag->letzterTagText() != '') {
                $titel .= ' ' . $text[4] . ' ' . $tag->letzterTagText();
            }
        }

        // die Paarungen der Runde, sortiert nach den Anstosszeiten ihrer Spiele: erst nach dem
        // ersten Spiel, bei Gleichheit nach dem zweiten usw.; sonst in der Reihenfolge der Paarungen
        $zeiten = static fn (Paarung $p): array => array_map(static fn (Spiel $s): int => $s->anstoss ?? 0, $p->spiele);
        $paarungen = iterator_to_array($liga->paarungen($st), false);
        usort($paarungen, static fn (Paarung $a, Paarung $b): int => $zeiten($a) <=> $zeiten($b));
        $bloecke = [];
        foreach ($paarungen as $paarung) {
            if ($paarung->heim <= 0 || $paarung->gast <= 0) {
                continue;
            }
            $i = $paarung->nr - 1;
            $zeilen = [];
            foreach ($paarung->spiele as $n => $spiel) {
                $zeilen[] = $this->zeile($kontext, $paarung, $spiel, $n) + [
                    // im Finale mit Spiel um Platz 3 steht vor jedem Spiel, um welches es geht
                    'kopf' => $platz3 ? ($i == 1 ? '&nbsp;<br>' : '') . $text[419 + $i] : null,
                ];
            }
            $bloecke[] = ['zeilen' => $zeilen, 'trenner' => count($paarung->spiele) > 1 && $i <= $anzahl - 1];
        }

        return new Seite(Vorlagen::zeige($kontext->pfade->vorlagen, 'pokal_ergebnisse.html.twig', [
            'text' => $text,
            'menue' => (new SpieltagsMenue($text, $kontext->pfade->vorlagen))->zeige($liga, $st, $rundenLink),
            'breite' => $breite,
            'titel' => $titel,
            'anstoss' => $e->mitAnstosszeit(),
            'berichte' => $e->mitSpielberichten(),
            'paarungen' => $bloecke,
            'zurueck' => $st > 1 ? $rundenLink . ($st - 1) : null,
            'vor' => $st < $runden ? $rundenLink . ($st + 1) : null,
        ]));
    }

    /** @return array<string, mixed> */
    private function zeile(Kontext $kontext, Paarung $paarung, Spiel $spiel, int $n): array
    {
        $geladen = $kontext->liga;
        assert($geladen !== null);
        $e = $geladen->liga->einstellungen();
        $text = $kontext->text;
        $datei = $geladen->datei;
        $bilder = $kontext->pfade->bilder;
        $heim = $paarung->heimMannschaft()->anzeigename();
        $gast = $paarung->gastMannschaft()->anzeigename();
        $toreHeim = $spiel->toreHeimText($e->torfaktor());
        $toreGast = $spiel->toreGastText($e->torfaktor());

        $anstoss = '';
        if ($spiel->anstoss !== null) {  // ohne Anstoss keine Anzeige
            $dt = new DateTime();
            $dt->setTimestamp($spiel->anstoss);
            $anstoss = $dt->format($e->datumsformat($kontext->konfiguration->datumsformat()));
        }

        // Heimrecht: im normalen Spiel steht die Heimmannschaft der Paarung links
        $modus = $e->playoffModus();
        $normal = match (true) {
            $modus == 1 || $modus == 2 => in_array($n, [0, 1, 4, 6], true),
            $modus == 3 => in_array($n, [0, 1, 5, 6], true),
            $modus < 1 => $n % 2 == 0,
            default => null,
        };
        $sieger = $paarung->sieger();  // 1 = Heim, 2 = Gast
        $favorit = $e->lieblingsmannschaft();
        $spielplan = $e->mitSpielplan() ? $kontext->adresse . '?action=program&amp;file=' . $datei . '&amp;selteam=' : null;
        $mannschaft = static fn (int $nr, string $name, bool $gewonnen): array => [
            'nr' => $nr,
            'name' => $name,
            'fett' => $favorit > 0 && $favorit == $nr,
            'sieger' => $gewonnen,
            'wappen' => $kontext->darstellung->wappen($datei, $name, ' alt=""'),
        ];
        $heimDaten = $mannschaft($paarung->heim, $heim, $sieger == 1);
        $gastDaten = $mannschaft($paarung->gast, $gast, $sieger == 2);

        /** Mannschaftsicons finden **/
        $mitWappen = $e->mitSpielberichten() || $spiel->notiz != '';
        $paarungHtml = SpielHinweis::paarung($heim, $mitWappen ? $heimDaten['wappen'] : '', $mitWappen ? $gastDaten['wappen'] : '', $gast);

        return [
            'anstoss' => $anstoss,
            'normal' => $normal,
            'links' => $normal === false ? $gastDaten : $heimDaten,
            'rechts' => $normal === false ? $heimDaten : $gastDaten,
            'tore_links' => $normal === false ? $toreGast : $toreHeim,
            'tore_rechts' => $normal === false ? $toreHeim : $toreGast,
            'spielplan' => $spielplan,
            'zusatz' => $spiel->zusatzText($text[0], $text[1]),
            /** Spielbericht verlinken **/
            'bericht' => $spiel->bericht != '' ? SpielHinweis::bericht($paarungHtml, $text[270], $bilder, $spiel->bericht) : '',
            /** Stats Addon */
            'addon' => $kontext->addons->zusatzJeSpiel($spiel, $kontext),
            /** Notizen anzeigen **/
            'notiz' => SpielHinweis::pokalNotiz($spiel, $text, $paarungHtml, $toreHeim, $toreGast, $bilder),
        ];
    }
}
