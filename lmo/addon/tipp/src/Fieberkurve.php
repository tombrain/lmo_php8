<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

/**
 * Die Werte der Fieberkurve je Tipper aus der Auswertung einer Liga (tipps/auswert/<liga>.aus):
 * die Punkte je Spieltag und auf Wunsch der Platz in der Wertung des einzelnen Spieltags. Bis 4.5
 * lmo-tippcalcgraph.php (Kurvenmodus 1 und 2; 3 und 4 bot die Seite nicht an).
 *
 * Die Werte bleiben, wie sie bis 4.5 in die Adresse des Bildes kamen: die Punkte als Text aus der
 * Datei, '' an Spieltagen ohne Eintrag bzw. ohne getippte Spiele.
 */
final class Fieberkurve
{
    /**
     * @param list<string> $nicks die Tipper in der Reihenfolge der Datei
     * @param array<int, array<int, string>> $punkte je Tipper und Spieltag (ab 0, einer mehr als die Liga hat)
     * @param array<int, array<int, int|string>> $plaetze je Tipper und Spieltag; nur mit $mitPlaetzen
     */
    private function __construct(public readonly array $nicks, public readonly array $punkte, public readonly array $plaetze)
    {
    }

    /**
     * Liest die Auswertung; null, wenn es sie nicht gibt.
     *
     * @param bool $mitPlaetzen auch die Plaetze je Spieltag (Kurvenmodus 2)
     * @param array{0: mixed, 1: mixed, 2: mixed} $kriterien krit1 bis krit3: Reihenfolge bei gleichen Punkten
     * @param bool $zusatz mit den Zusatzpunkten (showzus)
     * @param bool $siege mit den Spieltagssiegen (showstsiege)
     */
    public static function lies(string $pfad, int $spieltage, bool $mitPlaetzen, array $kriterien, bool $zusatz, bool $siege): ?self
    {
        if (!file_exists($pfad)) {
            return null;
        }
        // wie bis 4.5 zaehlt jeder Abschnitt als Tipper, ein Tipper beginnt aber erst mit seiner ersten Zeile
        $anzahl = 0;
        $zeilen = [];
        $abschnitt = '';
        foreach (file($pfad) ?: [] as $zeile) {
            $zeile = trim($zeile);
            if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                $abschnitt = trim(substr($zeile, 1, -1));
                $anzahl++;
            } elseif (str_contains($zeile, '=') && !str_starts_with($zeile, ';')) {
                $zeilen[] = [$abschnitt, trim(substr($zeile, 0, (int) strpos($zeile, '='))), trim(substr($zeile, (int) strpos($zeile, '=') + 1))];
            }
        }
        $leer = array_fill(0, $spieltage + 1, '');
        $nicks = array_fill(0, $anzahl + 1, '');
        $werte = [];
        foreach (['TP', 'SG', 'P1', 'P2', 'P3', 'P6'] as $art) {
            $werte[$art] = $anzahl > 0 ? array_fill(0, $anzahl, $leer) : [];
        }
        $t = 0;
        foreach ($zeilen as [$nick, $schluessel, $wert]) {
            if ($nicks[$t] != $nick) {
                if ($nicks[$t] != '') {
                    $t++;
                }
                $nicks[$t] = $nick;
            }
            $art = substr($schluessel, 0, 2);
            if ($art == 'TP' || $art == 'SG' || ($zusatz && in_array($art, ['P1', 'P2', 'P3', 'P6'], true))) {
                $werte[$art][$t][(int) substr($schluessel, 2) - 1] = $wert;
            }
        }
        $nicks = array_slice($nicks, 0, $anzahl);

        $plaetze = [];
        if ($mitPlaetzen) {
            $mitSiegen = $siege && ($kriterien[0] == 6 || $kriterien[1] == 6 || $kriterien[2] == 6);
            $stsiege = $mitSiegen ? self::spieltagssieger($werte, $anzahl, $spieltage, $kriterien, $zusatz) : [];
            $plaetze = $anzahl > 0 ? array_fill(0, $anzahl, $leer) : [];
            if ($anzahl > 0) {
                for ($j = 0; $j < $spieltage; $j++) {
                    $tab = [];
                    for ($a = 0; $a < $anzahl; $a++) {
                        $tt = (string) (50000000 + (int) $werte['TP'][$a][$j]);
                        foreach ($kriterien as $krit) {
                            $tt .= match (true) {
                                $krit == 6 => $siege ? (string) (50000000 + ($stsiege[$a][$j] ?? 0)) : '',
                                default => self::kriterium($krit, $werte, $a, $j, $zusatz),
                            };
                        }
                        $tab[] = $tt . (50000000 + $a);
                    }
                    rsort($tab, SORT_STRING);
                    $laenge = strlen($tab[0]);
                    for ($x = 0; $x < $anzahl; $x++) {
                        $x3 = intval(substr($tab[$x], -7));
                        if ($werte['SG'][$x3][$j] > 0) {
                            // gleiche Werte teilen sich den besten Platz
                            $y = $x;
                            while ($y > 0 && self::gleich($tab[$y], $tab[$y - 1], $laenge)) {
                                $y--;
                            }
                            $plaetze[$x3][$j] = $y + 1;
                        }
                    }
                }
            }
        }

        return new self($nicks, $werte['TP'], $plaetze);
    }

    /**
     * Die Spieltagssieger je Tipper und Spieltag (1 oder nichts), fuer das Kriterium 6.
     *
     * @param array<string, array<int, array<int, string>>> $werte
     * @param array{0: mixed, 1: mixed, 2: mixed} $kriterien
     * @return array<int, array<int, int>>
     */
    private static function spieltagssieger(array $werte, int $anzahl, int $spieltage, array $kriterien, bool $zusatz): array
    {
        $siege = [];
        for ($i = 0; $i < $spieltage; $i++) {
            $tab = [];
            for ($a = 0; $a < $anzahl; $a++) {
                $tt = (string) (50000000 + (int) $werte['TP'][$a][$i]);
                foreach ($kriterien as $krit) {
                    $tt .= self::kriterium($krit, $werte, $a, $i, $zusatz);
                }
                $tab[] = $tt . (50000000 + $a);
            }
            rsort($tab, SORT_STRING);
            $laenge = strlen($tab[0] ?? '');
            for ($a = 0; $a < $anzahl; $a++) {
                $x = intval(substr($tab[$a], -7));
                if ($werte['TP'][$x][$i] <= 0) {
                    break;
                }
                $siege[$x][$i] = ($siege[$x][$i] ?? 0) + 1;
                // wie bis 4.5 ohne Pruefung, ob es einen naechsten gibt (dann zaehlt er als 0)
                if (!self::gleich($tab[$a], $tab[$a + 1] ?? '', $laenge)) {
                    break;
                }
            }
        }

        return $siege;
    }

    /**
     * Ein Kriterium im Schluessel: -1 nichts (8 Nullen), 0 weniger Spiele, 1 mehr Spiele, mit
     * Zusatzpunkten 2 bis 5 die Punkte fuer Ergebnis, Tordifferenz, Tendenz und Joker.
     *
     * @param array<string, array<int, array<int, string>>> $werte
     */
    private static function kriterium(mixed $krit, array $werte, int $a, int $j, bool $zusatz): string
    {
        $wert = static fn (string $art): int => (int) ($werte[$art][$a][$j] ?? 0);

        return match (true) {
            $krit == -1 => '50000000',
            $krit == 0 => (string) (50000000 - $wert('SG')),
            $krit == 1 => (string) (50000000 + $wert('SG')),
            $zusatz && $krit == 2 => (string) (50000000 + $wert('P1')),
            $zusatz && $krit == 3 => (string) (50000000 + $wert('P2')),
            $zusatz && $krit == 4 => (string) (50000000 + $wert('P3')),
            $zusatz && $krit == 5 => (string) (50000000 + $wert('P6')),
            default => '',
        };
    }

    /** Ob zwei Schluessel bis auf die letzten beiden Werte gleich sind. */
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
}
