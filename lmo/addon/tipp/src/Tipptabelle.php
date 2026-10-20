<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Closure;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Spiel;

/**
 * Die Tabelle einer Liga, wie sie mit den Tipps aussaehe: jedes Spiel endet so, wie getippt wurde,
 * bei mehreren Tippern zaehlen die Tipps aller. Bis 4.5 lmo-tippcalctable.php mit dem direkten
 * Vergleich aus lmo-tippcalctable1.php.
 *
 * Gerechnet wird wie bis 4.5 ueber Sortierschluessel aus Text (je Wert 8 Stellen, 50000000 +
 * Wert); auch der direkte Vergleich arbeitet auf diesen Schluesseln, deshalb bleiben sie so.
 *
 *   $tabelle = Tipptabelle::rechne($liga, [$tipps], $verborgen, 0, 12);
 *   foreach ($tabelle->reihenfolge as $platz => $mannschaft) { $tabelle->werte[$mannschaft]['punkte'] ... }
 */
final class Tipptabelle
{
    /** @var array<int, array<int, Spiel>> */
    private array $spiele = [];

    /**
     * @param list<int> $reihenfolge die Mannschaften vom ersten zum letzten Platz
     * @param array<int, array{spiele: int, siege: int, unent: int, nieder: int, punkte: int, negativ: int, etore: int, atore: int, dtore: int, quote: float}> $werte je Mannschaft (ab 1); quote: Punkte je Spiel mal 100
     * @param int $bis Spieltag, bis zu dem gerechnet wurde (in der Hinrundentabelle deren letzter)
     */
    private function __construct(public readonly array $reihenfolge, public readonly array $werte, public readonly int $bis)
    {
    }

    /**
     * @param list<array<int, array<int, array{0: string, 1: string}>>> $tipper die Tipps je Tipper wie Tippdatei::alleRunden()
     * @param Closure(Spiel, int): bool $verborgen ob ein Spiel (mit seinem Spieltag ab 0) nicht zaehlt
     * @param int $art 0 gesamt, 1 heim, 2 auswaerts, 3 Rueckrunde, 4 Hinrunde
     * @param int $bis Spieltag, bis zu dem gerechnet wird
     */
    public static function rechne(Liga $liga, array $tipper, Closure $verborgen, int $art, int $bis): self
    {
        $rechner = new self([], [], $bis);
        $e = $liga->einstellungen();
        $anzahl = $e->mannschaften();
        $runden = $e->spieltage();
        $von = $art == 3 ? (int) ($runden / 2) : 0;
        if ($art == 4 && $anzahl > 0) {
            $bis = (int) ($runden / 2);
        }
        // ob ein Spiel zaehlt, rechnete 4.5 einmal fuer alle Tipper
        $zaehlt = [];
        for ($j = $von; $j < $bis; $j++) {
            for ($i = 0; $i < $e->partienJeSpieltag(); $i++) {
                $spiel = $rechner->spiel($liga, $j, $i);
                $zaehlt[$j][$i] = !$verborgen($spiel, $j) && !$spiel->ohneTippwertung;
            }
        }

        $leer = ['spiele' => 0, 'siege' => 0, 'unent' => 0, 'nieder' => 0, 'punkte' => 0, 'negativ' => 0, 'etore' => 0, 'atore' => 0, 'dtore' => 0, 'quote' => 0.0];
        $werte = array_fill(1, max($anzahl, 1), $leer);
        $alle = null;
        foreach ($tipper as $m => $tipps) {
            for ($a = 1; $a <= $anzahl; $a++) {
                $werte[$a] = $rechner->zaehle($werte[$a], $liga, $tipps, $zaehlt, $a, $art, $alle);
                if (in_array($art, [0, 3, 4], true) && $m == 0) {
                    $werte[$a]['punkte'] -= (int) ($liga->mannschaften[$a]->werte['SP'] ?? 0);
                    if ($e->mitMinuspunkten()) {
                        $werte[$a]['negativ'] -= (int) ($liga->mannschaften[$a]->werte['SM'] ?? 0);
                    }
                }
            }
        }
        $tab0 = [];
        for ($a = 1; $a <= $anzahl; $a++) {
            $werte[$a]['dtore'] = $werte[$a]['etore'] - $werte[$a]['atore'];
            $werte[$a]['quote'] = self::quote($werte[$a]['punkte'], $werte[$a]['spiele']);
            $tab0[] = self::schluessel($werte[$a], $e->istKegelliga(), [$a]);
        }
        rsort($tab0, SORT_STRING);
        if ($e->mitDirektemVergleich() && $tipper !== []) {
            // der direkte Vergleich nimmt wie bis 4.5 die Tipps des zuletzt gelesenen Tippers
            $tab0 = $rechner->direkterVergleich($tab0, $liga, $tipper[count($tipper) - 1], $zaehlt, $art);
        }
        $reihenfolge = [];
        foreach (array_slice($tab0, 0, $anzahl) as $zeile) {
            $reihenfolge[] = intval(substr($zeile, 42));
        }

        return new self($reihenfolge, array_slice($werte, 0, $anzahl, true), $bis);
    }

    /** Der Platz einer Mannschaft (ab 1), 0 wenn sie fehlt. */
    public function platz(int $mannschaft): int
    {
        $platz = array_search($mannschaft, $this->reihenfolge, true);

        return $platz === false ? 0 : $platz + 1;
    }

    /**
     * Zaehlt die getippten Spiele einer Mannschaft dazu; im direkten Vergleich nur die gegen die
     * Mannschaften in $nur, jedes Spiel hoechstens zweimal.
     *
     * @param array{spiele: int, siege: int, unent: int, nieder: int, punkte: int, negativ: int, etore: int, atore: int, dtore: int, quote: float} $w
     * @param array<int, array<int, array{0: string, 1: string}>> $tipps
     * @param array<int, array<int, bool>> $zaehlt
     * @param array{teams: list<string>, gezaehlt: array<int, array<int, int>>}|null $nur
     * @return array{spiele: int, siege: int, unent: int, nieder: int, punkte: int, negativ: int, etore: int, atore: int, dtore: int, quote: float}
     */
    private function zaehle(array $w, Liga $liga, array $tipps, array $zaehlt, int $a, int $art, ?array &$nur): array
    {
        $e = $liga->einstellungen();
        foreach ($zaehlt as $j => $spiele) {
            foreach ($spiele as $i => $ja) {
                if (!$ja) {
                    continue;
                }
                $spiel = $this->spiel($liga, $j, $i);
                $ta = $tipps[$j][$i][0] ?? Tippdatei::KEIN_TIPP;
                $tb = $tipps[$j][$i][1] ?? Tippdatei::KEIN_TIPP;
                $beteiligt = (in_array($art, [0, 3, 4], true) && ($a == $spiel->heim || $a == $spiel->gast))
                    || ($art == 1 && $a == $spiel->heim) || ($art == 2 && $a == $spiel->gast);
                if ($ta == Tippdatei::KEIN_TIPP || $tb == Tippdatei::KEIN_TIPP || !$beteiligt) {
                    continue;
                }
                if ($nur !== null) {
                    $b = 0;
                    foreach ($nur['teams'] as $team) {
                        if ($team == $spiel->heim || $team == $spiel->gast) {
                            $b++;
                        }
                    }
                    if ($b != 2 || ($nur['gezaehlt'][$j][$i] ?? 0) >= 2) {
                        continue;
                    }
                    $nur['gezaehlt'][$j][$i] = ($nur['gezaehlt'][$j][$i] ?? 0) + 1;
                }
                $ga = (int) $ta;
                $gb = (int) $tb;
                [$tore, $gegentore] = $a == $spiel->heim ? [$ga, $gb] : [$gb, $ga];
                $w['spiele']++;
                $w['etore'] += $tore;
                $w['atore'] += $gegentore;
                if ($tore > $gegentore) {
                    $w['siege']++;
                    $w['punkte'] += $e->punkteFuerSieg();
                    $w['negativ'] += $e->mitMinuspunkten() ? $e->punkteFuerNiederlage() : 0;
                } elseif ($tore < $gegentore) {
                    $w['nieder']++;
                    $w['punkte'] += $e->punkteFuerNiederlage();
                    $w['negativ'] += $e->mitMinuspunkten() ? $e->punkteFuerSieg() : 0;
                } else {
                    $w['unent']++;
                    $w['punkte'] += $e->punkteFuerUnentschieden();
                    $w['negativ'] += $e->mitMinuspunkten() ? $e->punkteFuerUnentschieden() : 0;
                }
            }
        }

        return $w;
    }

    /**
     * Punktgleiche Mannschaften nach dem direkten Vergleich ordnen (bis 4.5 lmo-tippcalctable1.php):
     * gleich sind sie, wenn die Zeichen 7 bis 15 der Schluessel gleich sind; ihr Platz im Vergleich
     * wird in den dritten Wert ihres Schluessels geschrieben.
     *
     * @param list<string> $tab0
     * @param array<int, array<int, array{0: string, 1: string}>> $tipps
     * @param array<int, array<int, bool>> $zaehlt
     * @return list<string>
     */
    private function direkterVergleich(array $tab0, Liga $liga, array $tipps, array $zaehlt, int $art): array
    {
        $e = $liga->einstellungen();
        $anzahl = $e->mannschaften();
        $cba = 1;
        for ($abc = 1; $abc < $anzahl; $abc++) {
            $x1 = substr($tab0[$abc - 1], 7, 9);
            $x2 = substr($tab0[$abc], 7, 9);
            if ($x1 == $x2) {
                $cba++;
            }
            $amEnde = $abc == $anzahl - 1 && $x1 == $x2;
            if ($x1 == $x2 && !$amEnde) {
                continue;
            }
            if ($cba > 1) {
                $teams = [];
                for ($b = 1; $b <= $cba; $b++) {
                    $teams[] = (string) intval(substr($tab0[$amEnde ? $abc - $b + 1 : $abc - $b], -7));
                }
                $nur = ['teams' => $teams, 'gezaehlt' => []];
                $tab1 = [];
                $leer = ['spiele' => 0, 'siege' => 0, 'unent' => 0, 'nieder' => 0, 'punkte' => 0, 'negativ' => 0, 'etore' => 0, 'atore' => 0, 'dtore' => 0, 'quote' => 0.0];
                for ($a = 1; $a <= $anzahl; $a++) {
                    $w = $this->zaehle($leer, $liga, $tipps, $zaehlt, $a, $art, $nur);
                    $w['dtore'] = $w['etore'] - $w['atore'];
                    $w['quote'] = self::quote($w['punkte'], $w['spiele']);
                    foreach ($teams as $c => $team) {
                        if ($team == $a) {
                            $tab1[] = self::schluessel($w, $e->istKegelliga(), [$c, $a]);
                        }
                    }
                }
                sort($tab1, SORT_STRING);
                $gezaehlt = array_sum(array_map('array_sum', $nur['gezaehlt']));
                if ($gezaehlt > 0) {
                    foreach ($tab1 as $nr => $zeile1) {
                        $b = (string) ($nr + 1);
                        foreach ($tab0 as $f => $zeile0) {
                            if (intval(substr($zeile0, -7)) == intval(substr($zeile1, -7))) {
                                $tab0[$f] = substr($zeile0, 0, 17 - strlen($b)) . $b . substr($zeile0, 17);
                            }
                        }
                    }
                }
            }
            $cba = 1;
        }
        rsort($tab0, SORT_STRING);

        return $tab0;
    }

    /** Punkte je Spiel mal 100, auf zwei Stellen gerundet wie bis 4.5. */
    private static function quote(int $punkte, int $spiele): float
    {
        return $spiele != 0 ? (float) number_format($punkte / $spiele, 2, '.', ',') * 100 : 0.0;
    }

    /**
     * Der Sortierschluessel: Quote, Punkte, Minuspunkte, Tordifferenz und Tore (in der Kegelliga
     * Tore vor Differenz), dahinter $anhang.
     *
     * @param array{spiele: int, siege: int, unent: int, nieder: int, punkte: int, negativ: int, etore: int, atore: int, dtore: int, quote: float} $w
     * @param list<int> $anhang
     */
    private static function schluessel(array $w, bool $kegeln, array $anhang): string
    {
        $werte = [50000000 + $w['quote'], 50000000 + $w['punkte'], 50000000 - $w['negativ']];
        array_push($werte, ...($kegeln ? [50000000 + $w['etore'], 50000000 + $w['dtore']] : [50000000 + $w['dtore'], 50000000 + $w['etore']]));
        foreach ($anhang as $wert) {
            $werte[] = 50000000 + $wert;
        }

        return implode('', array_map(static fn (int|float $wert): string => (string) $wert, $werte));
    }

    private function spiel(Liga $liga, int $j, int $i): Spiel
    {
        return $this->spiele[$j][$i] ??= Spiel::ausRohdaten($liga, $j + 1, $i + 1);
    }
}
