<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

/**
 * Die Rangliste der Tipper (oder ihrer Teams) aus der Auswertung einer Liga
 * (tipps/auswert/<liga>.aus) oder aller Ligen (gesamt.aus): je Tipper ein Abschnitt mit Team, Name,
 * Email und je Spieltag SG (getippte Spiele), TP (Punkte) und P1 bis P6 (wofuer es Punkte gab).
 * Bis 4.5 lmo-tippcalcwert.php (Tipper) und lmo-tippcalcwertteam.php (Teams).
 *
 * Gerechnet wird wie bis 4.5 ueber Sortierschluessel aus Text (je Wert 8 Stellen, 50000000 +
 * Wert); auch gleiche Plaetze erkennt die Seite an ihnen, deshalb gibt es sie nach aussen.
 *
 * @phpstan-type Eintrag array{nick: string, name: string, email: string, team: string, tipper: int, spiele: int|float, punkte: int|float, quote: int|float, p: array<int, int|float>, siege: int}
 */
final class Rangliste
{
    /**
     * @param list<Eintrag> $eintraege die Tipper bzw. Teams in der Reihenfolge der Datei; quote: Punkte je Spiel mal 100
     * @param list<string> $schluessel die Sortierschluessel vom ersten zum letzten Platz
     * @param int $bis Spieltag, bis zu dem gerechnet wurde (in der Gesamtwertung die Zahl der Ligen)
     */
    private function __construct(public readonly array $eintraege, public readonly array $schluessel, public readonly int $bis)
    {
    }

    /**
     * Liest die Auswertung; null, wenn es sie nicht gibt.
     *
     * @param bool $teams nach Teams statt nach Tippern
     * @param string $modus "bis" zum Spieltag $bis, "nur" der Spieltag $bis (ohne Stand alle)
     * @param bool $mitStand ein Spieltag ist gewaehlt (sonst zaehlt "nur" alle Spieltage)
     * @param int $spieltage Spieltage der Liga (0 in der Gesamtwertung); unter 1 gilt $bis bis hierhin
     * @param mixed $tippmodus Einstellung tippmodus: 1 Ergebnisse, 0 Tendenz (Quote in Prozent)
     * @param string $gewicht "absolut" nach Punkten, "relativ" nach Quote, "spiele" nach Spielen
     * @param array{0: mixed, 1: mixed, 2: mixed} $kriterien krit1 bis krit3: weitere Sortierung
     * @param bool $zusatz mit den Zusatzpunkten P1 bis P6 (showzus)
     * @param bool $siege mit den Spieltagssiegen (showstsiege)
     */
    public static function lies(string $pfad, bool $teams, string $modus, bool $mitStand, int $bis, int $spieltage, mixed $tippmodus, string $gewicht, array $kriterien, bool $zusatz, bool $siege): ?self
    {
        if (!file_exists($pfad)) {
            return null;
        }
        // die Zeilen als [Abschnitt, Schluessel, Wert]; die Abschnitte der Tipper beginnen mit ihrem Namen
        $zeilen = [];
        $abschnitt = '';
        $runden = $spieltage;
        $anzahl = 0;
        foreach (file($pfad) ?: [] as $zeile) {
            $zeile = trim($zeile);
            if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                $abschnitt = trim(substr($zeile, 1, -1));
                if (!$teams && $zeile != '[Options]') {
                    $zeilen[] = [$abschnitt, '', ''];
                    $anzahl++;
                }
            } elseif (str_contains($zeile, '=') && !str_starts_with($zeile, ';')) {
                $schluessel = trim(substr($zeile, 0, (int) strpos($zeile, '=')));
                $wert = trim(substr($zeile, (int) strpos($zeile, '=') + 1));
                if ($schluessel == 'AnzLigen') {
                    // nur in der Gesamtwertung
                    $bis = (int) $wert;
                    $runden = (int) $wert;
                    if ($teams) {
                        $zeilen[] = [$abschnitt, $schluessel, $wert];
                    }
                } elseif ($teams || $abschnitt != 'Options') {
                    $zeilen[] = [$abschnitt, $schluessel, $wert];
                }
            }
        }
        if ($bis < 1) {
            $bis = $runden;
        }
        $zaehlt = static fn (int $runde): bool => ($modus == 'nur' && (!$mitStand || $runde == $bis - 1)) || ($modus == 'bis' && $runde < $bis);
        $leer = ['nick' => '', 'name' => '', 'email' => '', 'team' => '', 'tipper' => 0, 'spiele' => 0, 'punkte' => 0, 'quote' => 0, 'p' => [], 'siege' => 0];

        // je Eintrag und Spieltag: SG, TP, P1 bis P6
        $werte = [];
        $eintraege = [];
        if (!$teams) {
            $t = 0;
            for ($a = 0; $a < $anzahl; $a++) {
                $eintraege[$a] = $leer;
                $werte[$a] = self::leereRunden($runden);
            }
            foreach ($zeilen as [$nick, $schluessel, $wert]) {
                if (($eintraege[$t]['nick'] ?? '') != $nick) {
                    if (($eintraege[$t]['nick'] ?? '') != '') {
                        $t++;
                    }
                    $eintraege[$t] ??= $leer;
                    $werte[$t] ??= self::leereRunden($runden);
                    $eintraege[$t]['nick'] = $nick;
                }
                $runde = (int) substr($schluessel, 2) - 1;
                $art = substr($schluessel, 0, 2);
                if ($schluessel == 'Team') {
                    $eintraege[$t]['team'] = $wert;
                } elseif ($schluessel == 'Name') {
                    $eintraege[$t]['name'] = $wert;
                } elseif ($schluessel == 'Email') {
                    $eintraege[$t]['email'] = $wert;
                } elseif ($zaehlt($runde) && ($art == 'SG' || $art == 'TP' || ($zusatz && in_array($art, ['P1', 'P2', 'P3', 'P4', 'P5', 'P6'], true)))) {
                    $werte[$t][$art][$runde] = self::zahl($wert);
                }
            }
        } else {
            // die Teams in der Reihenfolge, in der sie zuerst vorkommen
            foreach ($zeilen as [, $schluessel, $wert]) {
                if ($schluessel == 'Team' && $wert != '' && !in_array($wert, array_column($eintraege, 'team'), true)) {
                    $eintraege[] = ['team' => $wert] + $leer;
                    $werte[] = self::leereRunden($runden);
                }
            }
            // ein Tipper ohne Team-Zeile zaehlt fuer das Team des Tippers davor (wie bis 4.5)
            $t = 0;
            foreach ($zeilen as [, $schluessel, $wert]) {
                $runde = (int) substr($schluessel, 2) - 1;
                $art = substr($schluessel, 0, 2);
                if ($schluessel == 'Team') {
                    if ($wert == '' || $wert == '0') {
                        // Tipper hat kein Team
                        $t = -1;
                    } else {
                        $t = (int) array_search($wert, array_column($eintraege, 'team'), true);
                        $eintraege[$t]['tipper']++;
                    }
                } elseif ($zaehlt($runde) && $t > -1 && $runde > -1 && isset($werte[$t])
                    && ($art == 'SG' || $art == 'TP' || ($zusatz && in_array($art, ['P1', 'P2', 'P3', 'P4', 'P5', 'P6'], true)))) {
                    $werte[$t][$art][$runde] = ($werte[$t][$art][$runde] ?? 0) + ($art == 'SG' ? (int) $wert : self::zahl($wert));
                }
            }
        }
        $anzahl = count($eintraege);

        // alle Felder belegt (oben schon so angelegt; fuer PHPStan hier noch einmal)
        $eintraege = array_values(array_map(static fn (array $e): array => $e + $leer, $eintraege));
        if ($siege && ($teams || $anzahl > 0)) {
            $eintraege = self::spieltagssieger($eintraege, $werte, $bis, $kriterien, $zusatz);
        }

        $tab0 = [];
        foreach ($eintraege as $a => $e) {
            $e['spiele'] = array_sum($werte[$a]['SG']);
            $e['punkte'] = array_sum($werte[$a]['TP']);
            foreach (range(1, 6) as $nr) {
                $e['p'][$nr] = $zusatz ? array_sum($werte[$a]['P' . $nr]) : 0;
            }
            if ($e['spiele'] != 0) {
                $quote = $tippmodus == 1 ? $e['punkte'] / $e['spiele'] : ($tippmodus == 0 ? $e['punkte'] / $e['spiele'] * 100 : null);
                $e['quote'] = $quote === null ? 0 : (float) number_format($quote, 2, '.', ',') * 100;
            }
            $eintraege[$a] = $e;

            $tt = $gewicht == 'relativ' ? self::wert($e['quote']) : ($gewicht == 'spiele' ? self::wert($e['spiele']) : '');
            $tt .= self::wert($e['punkte']);
            foreach ($kriterien as $krit) {
                $tt .= match (true) {
                    $krit == 0 => self::wert($e['quote']),
                    $krit == 1 => self::wert($e['spiele']),
                    $krit == 6 => $siege ? self::wert($e['siege']) : '',
                    $zusatz && $krit == 2 => self::wert($e['p'][1]),
                    $zusatz && $krit == 3 => self::wert($e['p'][2]),
                    $zusatz && $krit == 4 => self::wert($e['p'][3]),
                    $zusatz && $krit == 5 => self::wert($e['p'][6]),
                    default => '',
                };
            }
            $tab0[] = $tt . (50000000 - $a) . (50000000 + $a);
        }
        rsort($tab0, SORT_STRING);

        return new self($eintraege, $tab0, $bis);
    }

    /** Der Eintrag (Index in eintraege) auf einem Platz (ab 0). */
    public function eintragAuf(int $platz): int
    {
        return intval(substr($this->schluessel[$platz] ?? '', -7));
    }

    /**
     * Die Plaetze (ab 1) je Eintrag.
     *
     * @return array<int, int>
     */
    public function plaetze(): array
    {
        $plaetze = [];
        foreach (array_keys($this->schluessel) as $x) {
            $plaetze[$this->eintragAuf($x)] = $x + 1;
        }

        return $plaetze;
    }

    /**
     * Ob die Plaetze $x und $x - 1 (ab 0) gleich sind: alle Werte ausser den letzten beiden
     * stimmen ueberein.
     */
    public function gleichAuf(int $x): bool
    {
        return self::gleich($this->schluessel[$x], $this->schluessel[$x - 1], strlen($this->schluessel[0]));
    }

    /**
     * Die Spieltagssieger (bis 4.5 im selben Durchlauf gezaehlt): je Spieltag die Besten mit
     * Punkten, bei Gleichstand alle. Wie bis 4.5 endet die Zaehlung nach einem Spieltag, auf dem
     * alle Eintraege durchgegangen wurden.
     *
     * @param list<Eintrag> $eintraege
     * @param array<int, array<string, array<int, int|float>>> $werte
     * @param array{0: mixed, 1: mixed, 2: mixed} $kriterien
     * @return list<Eintrag>
     */
    private static function spieltagssieger(array $eintraege, array $werte, int $bis, array $kriterien, bool $zusatz): array
    {
        $anzahl = count($eintraege);
        $a = 0;
        for ($i = 0; $i < $bis && isset($werte[$a]['TP'][$i]); $i++) {
            $tab = [];
            for ($a = 0; $a < $anzahl; $a++) {
                $tt = (string) (50000000 + ($werte[$a]['TP'][$i] ?? 0));
                foreach ($kriterien as $krit) {
                    $sg = $werte[$a]['SG'][$i] ?? 0;
                    $tt .= match (true) {
                        $krit == -1 => '50000000',
                        $krit == 0 => (string) (50000000 - $sg),
                        $krit == 1 => (string) (50000000 + $sg),
                        $zusatz && $krit == 2 => (string) (50000000 + (int) ($werte[$a]['P1'][$i] ?? 0)),
                        $zusatz && $krit == 3 => (string) (50000000 + ($werte[$a]['P2'][$i] ?? 0)),
                        $zusatz && $krit == 4 => (string) (50000000 + ($werte[$a]['P3'][$i] ?? 0)),
                        $zusatz && $krit == 5 => (string) (50000000 + ($werte[$a]['P6'][$i] ?? 0)),
                        default => '',
                    };
                }
                $tab[] = $tt . (50000000 + $a);
            }
            rsort($tab, SORT_STRING);
            $laenge = strlen($tab[0] ?? '');
            for ($a = 0; $a < $anzahl; $a++) {
                $x = intval(substr($tab[$a], -7));
                if (($werte[$x]['TP'][$i] ?? 0) <= 0) {
                    break;
                }
                if (isset($eintraege[$x])) {
                    $eintrag = $eintraege[$x];
                    $eintrag['siege']++;
                    $eintraege[$x] = $eintrag;
                }
                if (!isset($tab[$a + 1]) || !self::gleich($tab[$a], $tab[$a + 1], $laenge)) {
                    break;
                }
            }
        }

        return $eintraege;
    }

    /** Ob zwei Schluessel bis auf die letzten beiden Werte (bzw. den letzten bei den Spieltagssiegern) gleich sind. */
    private static function gleich(string $eins, string $zwei, int $laenge): bool
    {
        for ($k = 0; $k <= $laenge - 24; $k += 8) {
            if (intval(substr($eins, $k + 1, 7)) != intval(substr($zwei, $k + 1, 7))) {
                return false;
            }
            if ($k == $laenge - 24) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array<int, int|float>> */
    private static function leereRunden(int $runden): array
    {
        $leer = $runden > 0 ? array_fill(0, $runden, 0) : [];

        return ['SG' => $leer, 'TP' => $leer, 'P1' => $leer, 'P2' => $leer, 'P3' => $leer, 'P4' => $leer, 'P5' => $leer, 'P6' => $leer];
    }

    private static function zahl(string $wert): int|float
    {
        return is_numeric($wert) ? +$wert : 0;
    }

    private static function wert(int|float $wert): string
    {
        return (string) (50000000 + $wert);
    }
}
