<?php

declare(strict_types=1);

namespace Lmo\Web\Controller;

use DateTime;
use Lmo\Liga\Spiel;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Seiten\SpielHinweis;
use Lmo\Web\Vorlagen;

/**
 * Der Spielplan einer Mannschaft (action=program, nicht im Pokal): links die Auswahl der
 * Mannschaften, rechts alle Spiele der gewaehlten. Vorlage spielplan.html.twig.
 *
 * Parameter der Anfrage:
 *  - selteam  gewaehlte Mannschaft (ohne: die in der Liga vorgewaehlte, selTeam)
 *
 * Addons mit Zusatz je Spiel (Lmo\Addon\MitZusatzJeSpiel) haengen ihr HTML an jede Zeile, Addons
 * mit Zusatz unter der Seite (Lmo\Addon\MitZusatzUnterSeite) ihres unter den Spielplan.
 */
final class SpielplanController implements Controller
{
    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->liga !== null && !$kontext->liga->liga->istPokal() && $kontext->action == 'program';
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
        $datei = $geladen->datei;
        $gewaehlt = $kontext->anfrage->hat('selteam') ? $kontext->anfrage->zahl('selteam') : $e->vorgewaehlteMannschaft();

        $mannschaften = [];
        foreach ($liga->alleMannschaften() as $nr => $mannschaft) {
            $mannschaften[] = [
                'nr' => $nr,
                'name' => $mannschaft->anzeigename(),
                'kurz' => $mannschaft->kurzname(),
                'gewaehlt' => $nr == $gewaehlt,
                'wappen' => $kontext->darstellung->wappen($datei, $mannschaft->anzeigename(), ' alt=""'),
            ];
        }

        $spiele = [];
        if ($gewaehlt != 0) {
            foreach ($liga->alleSpiele() as $spiel) {
                if ($spiel->heim == $gewaehlt || $spiel->gast == $gewaehlt) {
                    $spiele[] = $this->zeile($spiel, $gewaehlt, $kontext);
                }
            }
        }

        $html = Vorlagen::zeige($kontext->pfade->vorlagen, 'spielplan.html.twig', [
            'text' => $kontext->text,
            'mannschaften' => $mannschaften,
            'gewaehlt' => $gewaehlt,
            'spiele' => $spiele,
            'link' => $kontext->adresse . '?action=program&amp;file=' . $datei . '&amp;selteam=',
            'spieltag_link' => $kontext->adresse . '?action=results&amp;file=' . $datei . '&amp;st=',
            'anstoss' => $e->mitAnstosszeit(),
            'verlaengerung' => $e->mitVerlaengerung(),
        ]);

        // Teamplan ADDON (z. B. der Spielplan als PDF)
        return new Seite($html . $kontext->addons->zusatzUnterSeite($kontext));
    }

    /** @return array<string, mixed> */
    private function zeile(Spiel $spiel, int $gewaehlt, Kontext $kontext): array
    {
        $geladen = $kontext->liga;
        assert($geladen !== null);
        $e = $geladen->liga->einstellungen();
        $text = $kontext->text;
        $datei = $geladen->datei;
        $bilder = $kontext->pfade->bilder;
        // steht eine Mannschaft noch nicht fest, heisst sie "___"
        $heim = $spiel->heim > 0 ? $spiel->heimMannschaft()->anzeigename() : '___';
        $gast = $spiel->gast > 0 ? $spiel->gastMannschaft()->anzeigename() : '___';
        $toreHeim = $spiel->toreHeimText($e->torfaktor());
        $toreGast = $spiel->toreGastText($e->torfaktor());
        $zusatz = $spiel->zusatzText($text[0], $text[1]);

        $anstoss = '';
        if ($spiel->anstoss !== null) {
            $dt = new DateTime();
            $dt->setTimestamp($spiel->anstoss);
            $anstoss = $dt->format($e->datumsformat($kontext->konfiguration->datumsformat()));
        }

        /** Mannschaftsicons finden */
        $wappenHeim = $kontext->darstellung->wappen($datei, $heim, ' alt=""');
        $wappenGast = $kontext->darstellung->wappen($datei, $gast, ' alt=""');
        $mitWappen = $e->mitSpielberichten() || $spiel->notiz != '' || $spiel->wertung > 0;
        $paarung = SpielHinweis::paarung($heim, $mitWappen ? $wappenHeim : '', $mitWappen ? $wappenGast : '', $gast);

        /** Spielbericht verlinken **/
        // null ohne die Spalte, '' wenn das Spiel keinen hat; im Spielplan ohne Link auf den Bericht
        $bericht = null;
        if ($e->mitSpielberichten()) {
            $bericht = $spiel->bericht != '' ? SpielHinweis::bericht($paarung, $text[270], $bilder, null) : '';
        }

        return [
            'spieltag' => $spiel->spieltag,
            'anstoss' => $anstoss,
            'heim' => ['name' => $heim, 'wappen' => $wappenHeim, 'fett' => $spiel->heim == $gewaehlt],
            'gast' => ['name' => $gast, 'wappen' => $wappenGast, 'fett' => $spiel->gast == $gewaehlt],
            'tore_heim' => $toreHeim,
            'tore_gast' => $toreGast,
            'zusatz' => $zusatz,
            'bericht' => $bericht,
            /** Notizen anzeigen **/
            'hinweis' => SpielHinweis::hinweis($spiel, $text, $paarung, $heim, $gast, $toreHeim, $toreGast, $e->mitVerlaengerung() ? $zusatz : null, $bilder, true),
            /** bisherige Ergebnisse (AddOn Team Vergleich) */
            'addon' => $kontext->addons->mitZusatzJeSpiel() ? $kontext->addons->zusatzJeSpiel($spiel, $kontext) : null,
        ];
    }
}
