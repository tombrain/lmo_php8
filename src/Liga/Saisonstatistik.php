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

namespace Lmo\Liga;

/**
 * Die Statistik einer Saison (bis 4.5 lmo-zustat.php): Tore je Spieltag, Siege, Rekorde und
 * Serien. Sie steht als PHP-Code in output/<liga>.l98.php und wird von Lmo\Web\Statistikdatei
 * gelesen (Statistikseite, Torstatistik unter den Ergebnissen). Nur fuer Ligen, nicht im Pokal.
 *
 * Die Berechnung folgt dem alten Code samt seinen Eigenheiten (z. B. bei Wertungen am gruenen
 * Tisch), damit die Seiten dieselben Zahlen zeigen.
 */
final class Saisonstatistik
{
    private const SERIEN = ['gewonnen', 'ungeschlagen', 'unentschieden', 'sieglos', 'verloren'];

    /**
     * Schreibt die Datei; im Pokal nichts.
     *
     * @param string $spieltagKurz Sprachtext 145 ("Spieltag" hinter den Spieltagen einer Serie)
     */
    public static function schreibe(Rohdaten\Liga $liga, string $pfad, string $spieltagKurz): void
    {
        if ($liga->einstellungen()->istPokal()) {
            return;
        }
        file_put_contents($pfad, self::alsPhp($liga, $spieltagKurz));
    }

    /**
     * Der Inhalt der Datei.
     *
     * @param string $spieltagKurz Sprachtext 145
     */
    public static function alsPhp(Rohdaten\Liga $liga, string $spieltagKurz): string
    {
        $einstellungen = $liga->einstellungen();
        $spieltage = $einstellungen->spieltage();
        $partien = $einstellungen->partienJeSpieltag();
        $mannschaften = $einstellungen->mannschaften();

        $aus = "<?php\n";
        $gzutore = 0;
        $gzuanzsp = 0;
        $gzusieg1 = 0;
        $gzusieg2 = 0;
        $gzuunent = 0;
        $gheimtore = 0;
        $ggasttore = 0;
        $gbeide = 0;
        $gdstore = 0;
        $dsheimtore = 0;
        $dsgasttore = 0;

        // hoechster Heimsieg, hoechster Auswaertssieg, torreichstes Spiel: je der erste Rekord und
        // ein zweites Spiel mit demselben Ergebnis
        $hsieg1 = 0;
        $asieg1 = 0;
        $h = ['heim' => '0', 'gast' => '0', 'toreHeim' => 0, 'toreGast' => 0, 'st' => 0];
        $h1 = $h;
        $a = $h;
        $a1 = $h;
        $t = $h;
        $t1 = $h;
        $htorreich1 = 0;
        $htorreich2 = 0;

        /** @var array<string, array<string, int>> $laufend Serien je Mannschaft und Art */
        $laufend = [];
        /** @var array<string, array<string, array{int, string}>> $laengste je Mannschaft und Art: Laenge und Spieltage */
        $laengste = [];
        /** @var array<string, array<int, int>> $ergebnisse je Mannschaft und Spieltag: 1 Sieg, 0 Remis, -1 Niederlage */
        $ergebnisse = [];

        for ($st = 1; $st <= $spieltage; $st++) {
            $sptore1 = 0;
            $zuanzsp = 0;
            $zusieg1 = 0;
            $zusieg2 = 0;
            $zuunent1 = 0;

            for ($p = 1; $p <= $partien; $p++) {
                $spiel = Spiel::ausRohdaten($liga, $st, $p);
                // Partien mit einer Mannschaft, die es in der Liga nicht gibt (z.B. Spielplan aus
                // einer Vorlage mit mehr Mannschaften), zaehlen nicht
                foreach ([$spiel->heim, $spiel->gast] as $nr) {
                    if ($nr <= 0 || ($nr > $mannschaften + 1 && !isset($liga->mannschaften[$nr]))) {
                        continue 2;
                    }
                }
                $heimteam = $spiel->heimMannschaft()->anzeigename();
                $gastteam = $spiel->gastMannschaft()->anzeigename();
                $heimtore = $spiel->toreHeim;
                $gasttore = $spiel->toreGast;

                // Wertung am gruenen Tisch
                if ($spiel->wertung === 1) {
                    $zusieg1++;
                    $zuunent1--;
                } elseif ($spiel->wertung === 2) {
                    $zusieg2++;
                    $zuunent1--;
                } elseif ($spiel->wertung === 3) {
                    $gbeide++;
                    // bis 4.5 brach das mit nur einer Seite eingetragen ab
                    if ($heimtore !== null && $gasttore !== null) {
                        if ($heimtore > $gasttore) {
                            $sptore1 += $heimtore;
                            $zusieg2++;
                            $ggasttore += $heimtore;
                        }
                        if ($heimtore < $gasttore) {
                            $sptore1 += $gasttore;
                            $zusieg2--;
                            $gheimtore += $gasttore;
                        }
                    }
                }

                if ($heimtore === null || $gasttore === null) {
                    continue;
                }
                $hsieg = $heimtore - $gasttore;
                $ergebnisse[$heimteam][$st - 1] = $hsieg <=> 0;
                $ergebnisse[$gastteam][$st - 1] = 0 <=> $hsieg;
                foreach (self::SERIEN as $art) {
                    foreach ([$heimteam, $gastteam] as $team) {
                        self::serieWeiter($laufend, $laengste, $ergebnisse, $team, $art, $st);
                    }
                }

                // hoechster Heimsieg
                if ($h['toreHeim'] === $heimtore && $h['toreGast'] === $gasttore) {
                    $h1 = ['heim' => $heimteam, 'gast' => $gastteam, 'toreHeim' => $heimtore, 'toreGast' => $gasttore, 'st' => $st];
                }
                if ($hsieg > 0 && ($hsieg > $hsieg1 || ($hsieg === $hsieg1 && $h['toreGast'] < $gasttore && $h['toreHeim'] < $heimtore))) {
                    $h = ['heim' => $heimteam, 'gast' => $gastteam, 'toreHeim' => $heimtore, 'toreGast' => $gasttore, 'st' => $st];
                    $hsieg1 = $hsieg;
                }

                // hoechster Auswaertssieg
                $asieg = $gasttore - $heimtore;
                if ($a['toreHeim'] === $heimtore && $a['toreGast'] === $gasttore) {
                    $a1 = ['heim' => $heimteam, 'gast' => $gastteam, 'toreHeim' => $heimtore, 'toreGast' => $gasttore, 'st' => $st];
                }
                if ($asieg > 0 && ($asieg > $asieg1 || ($asieg === $asieg1 && $a['toreGast'] < $gasttore && $a['toreHeim'] < $heimtore))) {
                    $a = ['heim' => $heimteam, 'gast' => $gastteam, 'toreHeim' => $heimtore, 'toreGast' => $gasttore, 'st' => $st];
                    $asieg1 = $asieg;
                }

                // torreichstes Spiel
                $htorreich = $heimtore + $gasttore;
                if ($htorreich1 === $htorreich) {
                    $t1 = ['heim' => $heimteam, 'gast' => $gastteam, 'toreHeim' => $heimtore, 'toreGast' => $gasttore, 'st' => $st];
                    $htorreich2 = $htorreich;
                }
                if ($htorreich > 0 && $htorreich > $htorreich1) {
                    $t = ['heim' => $heimteam, 'gast' => $gastteam, 'toreHeim' => $heimtore, 'toreGast' => $gasttore, 'st' => $st];
                    $htorreich1 = $htorreich;
                }

                // Anzahl und Art der Spiele
                $gheimtore += $heimtore;
                $ggasttore += $gasttore;
                $sptore1 += $heimtore + $gasttore;
                $zuanzsp++;
                if ($heimtore > $gasttore) {
                    $zusieg1++;
                } elseif ($heimtore < $gasttore) {
                    $zusieg2++;
                } else {
                    $zuunent1++;
                }
            }
            $gzutore += $sptore1;
            $gzuanzsp += $zuanzsp;
            $gzusieg1 += $zusieg1;
            $gzusieg2 += $zusieg2;
            $gzuunent += $zuunent1;

            $aus .= '$zutore[' . $st . ']=' . $sptore1 . ";\n";  // Tore am Spieltag
            if ($gzuanzsp > 0) {
                $gdstore = round($gzutore / $gzuanzsp, 2);
                $dsheimtore = round($gheimtore / $gzuanzsp, 2);
                $dsgasttore = round($ggasttore / $gzuanzsp, 2);
            } else {
                $gdstore = 0;
                $dsheimtore = 0;
                $dsgasttore = 0;
            }
            // Tore je Spiel am Spieltag
            $aus .= '$dstore[' . $st . ']=' . ($zuanzsp > 0 ? round($sptore1 / $zuanzsp, 2) : 0) . ";\n";
        }

        // das zweite Spiel nur, wenn es wirklich dasselbe Ergebnis hat
        $leer = ['heim' => '0', 'gast' => '0', 'toreHeim' => 0, 'toreGast' => 0, 'st' => 0];
        if ($h['toreHeim'] !== $h1['toreHeim'] || $h['toreGast'] !== $h1['toreGast']) {
            $h1 = $leer;
        }
        if ($a['toreHeim'] !== $a1['toreHeim'] || $a['toreGast'] !== $a1['toreGast']) {
            $a1 = $leer;
        }
        if ($t['toreHeim'] === 0 && $t['toreGast'] === 0) {
            $t = $leer;
            $t1 = $leer;
        }
        if ($htorreich1 !== $htorreich2) {
            $t1 = $leer;
        }

        // Anzahl der Spiele mit dem Rekordergebnis
        $counteranz = 0;
        $counteranz1 = 0;
        $counteranz5 = 0;
        for ($st = 1; $st <= $spieltage; $st++) {
            for ($p = 1; $p <= $partien; $p++) {
                $spiel = Spiel::ausRohdaten($liga, $st, $p);
                if (!$spiel->istGespielt()) {
                    continue;
                }
                if ($spiel->toreHeim === $h['toreHeim'] && $spiel->toreHeim === $h1['toreHeim'] && $spiel->toreGast === $h['toreGast'] && $spiel->toreGast === $h1['toreGast']) {
                    $counteranz++;
                }
                if ($spiel->toreHeim === $a['toreHeim'] && $spiel->toreHeim === $a1['toreHeim'] && $spiel->toreGast === $a['toreGast'] && $spiel->toreGast === $a1['toreGast']) {
                    $counteranz1++;
                }
                if ($t['toreHeim'] + $t['toreGast'] === $spiel->toreHeim + $spiel->toreGast) {
                    $counteranz5++;
                }
            }
        }

        // Serien: die laufenden und die laengsten, mit allen Mannschaften, die sie halten
        $akt = array_fill_keys(self::SERIEN, 0);
        $max = $akt;
        $teamAkt = array_fill_keys(self::SERIEN, []);
        $teamMax = $teamAkt;
        foreach ($laufend as $team => $serien) {
            $team = (string) $team;
            foreach (self::SERIEN as $art) {
                $laenge = $serien[$art];
                if ($laenge > $akt[$art]) {
                    $akt[$art] = $laenge;
                    $teamAkt[$art] = [$team];
                } elseif ($laenge === $akt[$art] && $laenge > 0) {
                    $teamAkt[$art][] = $team;
                }
            }
            foreach (self::SERIEN as $art) {
                [$laenge, $spieltageSerie] = $laengste[$team][$art];
                $eintrag = $team . ' (' . $spieltageSerie . '.' . $spieltagKurz . ')';
                if ($laenge > $max[$art]) {
                    $max[$art] = $laenge;
                    $teamMax[$art] = [$eintrag];
                } elseif ($laenge === $max[$art] && $max[$art] > 0) {
                    $teamMax[$art][] = $eintrag;
                }
            }
        }

        $aus .= self::zahl('gzutore', $gzutore) . self::zahl('gdstore', $gdstore) . self::zahl('gzusieg1', $gzusieg1) . self::zahl('gzusieg2', $gzusieg2)
            . self::zahl('gzuunent', $gzuunent) . self::zahl('gbeide', $gbeide) . self::zahl('gheimtore', $gheimtore) . self::zahl('ggasttore', $ggasttore)
            . self::zahl('dsheimtore', $dsheimtore) . self::zahl('dsgasttore', $dsgasttore)
            . self::rekord($h, ['hheimsieg', 'hgastsieg', 'hheimsiegtor', 'hgastsiegtor', 'spieltagflag'])
            . self::rekord($h1, ['hheimsieg1', 'hgastsieg1', 'hheimsiegtor1', 'hgastsiegtor1', 'spieltagflag1'])
            . self::zahl('counteranz', $counteranz)
            . self::rekord($a, ['aheimsieg', 'agastsieg', 'aheimsiegtor', 'agastsiegtor', 'spieltagflag2'])
            . self::rekord($a1, ['aheimsieg1', 'agastsieg1', 'aheimsiegtor1', 'agastsiegtor1', 'spieltagflag3'])
            . self::zahl('counteranz1', $counteranz1)
            . self::rekord($t, ['htorreichm1', 'htorreichm2', 'htorreicht1', 'htorreicht2', 'spieltagflag4'])
            . self::rekord($t1, ['htorreichm3', 'htorreichm4', 'htorreicht3', 'htorreicht4', 'spieltagflag5'])
            . self::zahl('counteranz5', $counteranz5);
        foreach (self::SERIEN as $art) {
            $aus .= self::zahl('akt_' . $art, $akt[$art]) . self::html('team_akt_' . $art, implode("\n", $teamAkt[$art]));
        }
        foreach (self::SERIEN as $art) {
            $aus .= self::zahl('max_' . $art, $max[$art]) . self::html('team_max_' . $art, implode("\n", $teamMax[$art]));
        }

        return $aus . '?>';
    }

    /** Eine Zahl als Zuweisung in der Datei */
    private static function zahl(string $name, int|float $wert): string
    {
        return '$' . $name . '=' . $wert . ";\n";
    }

    /**
     * Ein Text als Zuweisung in der Datei, in doppelten Anfuehrungszeichen: $ und \ maskiert, sonst
     * liefe ein Name wie {${...}} beim Einbinden der Datei als PHP-Code.
     */
    private static function text(string $name, string $wert): string
    {
        return '$' . $name . '="' . addcslashes($wert, '\\$') . "\";\n";
    }

    /** Ein Text wie text(), vorher fuer HTML maskiert (Namen der Mannschaften) */
    private static function html(string $name, string $wert): string
    {
        return self::text($name, htmlspecialchars($wert));
    }

    /**
     * Ein Rekord: Heim, Gast, Tore beider und Spieltag.
     *
     * @param array{heim: string, gast: string, toreHeim: int, toreGast: int, st: int} $r
     * @param array{string, string, string, string, string} $namen
     */
    private static function rekord(array $r, array $namen): string
    {
        return self::html($namen[0], $r['heim']) . self::html($namen[1], $r['gast'])
            . self::text($namen[2], (string) $r['toreHeim']) . self::text($namen[3], (string) $r['toreGast']) . self::zahl($namen[4], $r['st']);
    }

    /**
     * Fuehrt eine Serie einer Mannschaft nach ihrem Spiel am Spieltag fort: weiter, wenn sie auch am
     * Spieltag davor dazu passte, sonst neu; dazu die laengste (bei gleicher Laenge die spaetere).
     *
     * @param array<string, array<string, int>> $laufend
     * @param array<string, array<string, array{int, string}>> $laengste
     * @param array<string, array<int, int>> $ergebnisse
     */
    private static function serieWeiter(array &$laufend, array &$laengste, array $ergebnisse, string $team, string $art, int $st): void
    {
        $passt = static fn (int $e): bool => match ($art) {
            'gewonnen' => $e > 0,
            'ungeschlagen' => $e >= 0,
            'unentschieden' => $e === 0,
            'sieglos' => $e <= 0,
            default => $e < 0,
        };
        $vorher = $ergebnisse[$team][$st - 2] ?? null;
        if (!$passt($ergebnisse[$team][$st - 1])) {
            $laenge = 0;
        } elseif (isset($laufend[$team][$art]) && $vorher !== null && $passt($vorher)) {
            $laenge = $laufend[$team][$art] + 1;
        } else {
            $laenge = 1;
        }
        $laufend[$team][$art] = $laenge;
        if ($laenge >= ($laengste[$team][$art][0] ?? 0)) {
            $laengste[$team][$art] = [$laenge, ($st - ($laenge - 1)) . '-' . $st];
        }
    }
}
