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
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Seiten\SpielHinweis;
use Lmo\Web\Vorlagen;

/**
 * Der Spielplan einer Mannschaft im Pokal (action=program im Pokal): links und rechts die
 * Auswahl der Mannschaften, in der Mitte alle Paarungen der gewaehlten mit ihren Spielen.
 * Vorlage pokal_spielplan.html.twig.
 *
 * Parameter der Anfrage:
 *  - selteam  gewaehlte Mannschaft (ohne: die im Pokal vorgewaehlte, selTeam)
 */
final class PokalSpielplanController implements Controller
{
    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->liga !== null && $kontext->liga->liga->istPokal() && $kontext->action == 'program';
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        assert($geladen !== null);
        $liga = $geladen->liga;
        $e = $liga->einstellungen();
        // die Liga kann den Spielplan abschalten
        if (!$e->mitSpielplan()) {
            return new Seite('');
        }
        $text = $kontext->text;
        $datei = $geladen->datei;
        $gewaehlt = $kontext->anfrage->hat('selteam') ? $kontext->anfrage->zahl('selteam') : $e->vorgewaehlteMannschaft();
        $anzahl = $e->mannschaften();
        $runden = $e->spieltage();

        $mannschaften = [];
        foreach ($liga->alleMannschaften() as $nr => $mannschaft) {
            $mannschaften[$nr] = [
                'nr' => $nr,
                'name' => $mannschaft->anzeigename(),
                'kurz' => $mannschaft->kurzname(),
                'gewaehlt' => $nr == $gewaehlt,
                'wappen' => $kontext->darstellung->wappen($datei, $mannschaft->anzeigename(), ' alt=""'),
            ];
        }
        // die Auswahl links und rechts: erste und zweite Haelfte der Mannschaften; bei ungerader
        // Anzahl steht die mittlere links (bis 4.5 fehlte sie)
        $links = $rechts = [];
        for ($i = 1; $i <= ceil($anzahl / 2); $i++) {
            $links[] = $mannschaften[$i];
        }
        for ($i = (int) ceil($anzahl / 2) + 1; $i <= $anzahl; $i++) {
            $rechts[] = $mannschaften[$i];
        }

        $zeilen = [];
        if ($gewaehlt != 0) {
            // $j: Runde ab 0
            for ($j = 0; $j < $runden; $j++) {
                foreach ($liga->paarungen($j + 1) as $paarung) {
                    if ($gewaehlt == $paarung->heim || $gewaehlt == $paarung->gast) {
                        $zeilen[] = $this->zeile($kontext, $paarung, $j, $runden, $gewaehlt);
                    }
                }
            }
        }

        return new Seite(Vorlagen::zeige($kontext->pfade->vorlagen, 'pokal_spielplan.html.twig', [
            'text' => $text,
            'bilder' => $kontext->pfade->bilder,
            'link' => $kontext->adresse . '?action=program&amp;file=' . $datei . '&amp;selteam=',
            'runden_link' => $kontext->adresse . '?action=results&amp;file=' . $datei . '&amp;st=',
            'links' => $links,
            'rechts' => $rechts,
            'gewaehlt' => $gewaehlt,
            'zeilen' => $zeilen,
        ]));
    }

    /** @return array<string, mixed> */
    private function zeile(Kontext $kontext, Paarung $paarung, int $j, int $runden, int $gewaehlt): array
    {
        $geladen = $kontext->liga;
        assert($geladen !== null);
        $e = $geladen->liga->einstellungen();
        $text = $kontext->text;
        $datei = $geladen->datei;
        $bilder = $kontext->pfade->bilder;
        // steht eine Mannschaft noch nicht fest, heisst sie "___"
        $heim = $paarung->heim > 0 ? $paarung->heimMannschaft()->anzeigename() : '___';
        $gast = $paarung->gast > 0 ? $paarung->gastMannschaft()->anzeigename() : '___';
        $wappenHeim = $kontext->darstellung->wappen($datei, $heim, ' alt=""');
        $wappenGast = $kontext->darstellung->wappen($datei, $gast, ' alt=""');
        $vereinslinks = $e->mitMannschaftslinks();

        // Name der Runde: die letzten vier heissen Finale, Halbfinale usw.
        $vomEnde = $runden - 1 - $j;
        $runde = $vomEnde <= 3 ? $text[364 - 2 * $vomEnde] : (string) ($j + 1);

        $spiele = [];
        foreach ($paarung->spiele as $spiel) {
            $toreHeim = $spiel->toreHeimText($e->torfaktor());
            $toreGast = $spiel->toreGastText($e->torfaktor());
            $anstoss = null;
            if ($e->mitAnstosszeit() && $spiel->anstoss !== null) {  // ohne Anstoss keine Anzeige
                $dt = new DateTime();
                $dt->setTimestamp($spiel->anstoss);
                $anstoss = $dt->format($e->datumsformat($kontext->konfiguration->datumsformat()));
            }
            /** Mannschaftsicons finden **/
            $mitWappen = $e->mitSpielberichten() || $spiel->notiz != '';
            $paarungHtml = SpielHinweis::paarung($heim, $mitWappen ? $wappenHeim : '', $mitWappen ? $wappenGast : '', $gast);
            $spiele[] = [
                'anstoss' => $anstoss,
                'ergebnis' => $toreHeim . '&nbsp;:&nbsp;' . $toreGast . $spiel->zusatzText($text[0], $text[1]),
                /** Spielbericht verlinken **/
                'bericht' => $spiel->bericht != '' ? SpielHinweis::bericht($paarungHtml, $text[270], $bilder, $spiel->bericht) : '',
                /** Notizen anzeigen **/
                'notiz' => SpielHinweis::pokalNotiz($spiel, $text, $paarungHtml, $toreHeim, $toreGast, $bilder),
            ];
        }

        return [
            'runde' => $j + 1,
            'name' => $runde,
            'sieger' => $paarung->sieger(),  // 1 = Heim, 2 = Gast
            'heim' => ['name' => $heim, 'wappen' => $wappenHeim, 'fett' => $gewaehlt == $paarung->heim, 'url' => $vereinslinks ? ($paarung->heimMannschaft()->werte['URL'] ?? '') : ''],
            'gast' => ['name' => $gast, 'wappen' => $wappenGast, 'fett' => $gewaehlt == $paarung->gast, 'url' => $vereinslinks ? ($paarung->gastMannschaft()->werte['URL'] ?? '') : ''],
            'berichte' => $e->mitSpielberichten(),
            'spiele' => $spiele,
        ];
    }
}
