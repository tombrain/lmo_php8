<?php

namespace Lmo\Web\Controller;

use Closure;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Tabelle;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Fieberkurve einer Liga (action=graph, nicht im Pokal): der Platz jeder Mannschaft nach
 * jedem Spieltag als Liniendiagramm (js/chart.js). Vorlage fieberkurve.html.twig.
 *
 * Parameter der Anfrage:
 *  - stat1, stat2  die Mannschaften, deren Kurven zu Beginn sichtbar sind (ohne: die der Liga,
 *                  kurve1/kurve2; ohne beide die ersten zwei Mannschaften)
 */
final class FieberkurveController implements Controller
{
    /** @var Closure(): string zufaellige Linienfarbe "r,g,b,1" */
    private Closure $farbe;

    /** @param (Closure(): string)|null $farbe Linienfarbe je Mannschaft; null: zufaellig wie bisher */
    public function __construct(?Closure $farbe = null)
    {
        $this->farbe = $farbe ?? static fn (): string => mt_rand(0, 160) . ',' . mt_rand(0, 160) . ',' . mt_rand(0, 160) . ',1';
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->liga !== null && !$kontext->liga->liga->istPokal() && $kontext->action == 'graph';
    }

    public function zeige(Kontext $kontext): Seite
    {
        $geladen = $kontext->liga;
        assert($geladen !== null);
        $liga = $geladen->liga;
        $e = $liga->einstellungen();
        // die Liga kann die Fieberkurve abschalten
        if (!$e->mitFieberkurve()) {
            return new Seite('');
        }
        $anfrage = $kontext->anfrage;
        $erste = $anfrage->gesetzt('stat1') ? $anfrage->zahl('stat1') : $e->fieberkurveErste();
        $zweite = $anfrage->gesetzt('stat2') ? $anfrage->zahl('stat2') : $e->fieberkurveZweite();
        if ($erste == 0 && $zweite != 0 || $erste == $zweite) {
            $erste = $zweite;
            $zweite = 0;
        }

        $platz = self::plaetze($liga);
        $kurven = [];
        foreach ($liga->alleMannschaften() as $nr => $mannschaft) {
            // Spieltage, an denen die Liga nicht gespielt hat, zaehlen nicht (Platz 0); wie bisher
            // endet die Reihe mit einem Komma, und Spieltage ohne Platz bleiben als Luecke stehen
            $reihe = array_slice($platz[$nr], 0, $e->spieltage());
            $reihe[] = '';
            $plaetze = array_filter($reihe, static fn (int|string $p): bool => $p !== 0);
            if ($erste == 0) {  // dann ist nach dem Tausch oben auch $zweite 0
                $versteckt = $nr >= 3;
            } else {
                $versteckt = $nr != $erste && $nr != $zweite;
            }
            $kurven[] = [
                //Team names with an apostrophe inside destroy the graph curve
                'name' => str_replace("'", '´', $mannschaft->anzeigename()),
                'farbe' => ($this->farbe)(),
                'plaetze' => implode(',', $plaetze),
                'versteckt' => $versteckt,
            ];
        }

        return new Seite(Vorlagen::zeige($kontext->pfade->vorlagen, 'fieberkurve.html.twig', [
            'text' => $kontext->text,
            'adresse' => $kontext->pfade->adresse,
            'spieltage' => $e->spieltage() > 0 ? implode(',', range(1, $e->spieltage())) : '',
            'mannschaften' => $e->mannschaften(),
            'kurven' => $kurven,
        ]));
    }

    /**
     * Platz jeder Mannschaft nach jedem Spieltag: je Spieltag die Gesamttabelle bis dorthin
     * (Lmo\Liga\Tabelle), also mit denselben Regeln wie die Tabellenseite; fuehrt die Liga ein
     * Handicap, gilt dessen Reihenfolge. 0: an dem Spieltag hat keine Mannschaft gespielt.
     *
     * @return array<int, array<int, int|string>> Nummer der Mannschaft => Spieltag ab 0 => Platz
     */
    public static function plaetze(Liga $liga): array
    {
        $e = $liga->einstellungen();
        $anzahl = $e->mannschaften();
        $spieltage = $e->spieltage();
        $platz = [];
        for ($i = 0; $i <= $anzahl; $i++) {
            $platz[$i] = array_fill(0, $spieltage + 1, '');
        }
        for ($j = 0; $j < $spieltage; $j++) {
            // hat an diesem Spieltag eine Mannschaft der Liga gespielt?
            $gespielt = false;
            foreach ($liga->spiele($j + 1) as $spiel) {
                if ($spiel->istGespielt() && (($spiel->heim >= 1 && $spiel->heim <= $anzahl) || ($spiel->gast >= 1 && $spiel->gast <= $anzahl))) {
                    $gespielt = true;
                    break;
                }
            }
            $tabelle = Tabelle::fuerAnsicht($liga, Tabelle::GESAMT, $j + 1);
            $reihenfolge = $tabelle->reihenfolge;
            if ($e->mitHandicap() && isset($liga->spieltage[$j + 1])) {
                $reihenfolge = $tabelle->reihenfolgeNachHandicap($liga->spieltage[$j + 1]->werte['HS'] ?? '0');
            }
            foreach ($reihenfolge as $x => $nr) {
                $platz[$nr][$j] = $gespielt ? $x + 1 : 0;
            }
        }

        return $platz;
    }
}
