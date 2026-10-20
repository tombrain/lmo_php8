<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

/**
 * Wie viele Punkte es fuer einen Tipp gibt, nach den Regeln des Tippspiels (tipp/cfg.txt). Bis
 * 4.5 tipppunkte() mit globalen Variablen in lmo-tippcalcpkt.php.
 *
 * Neben den Punkten zaehlt die Wertung, wofuer es sie gab (Zusatzauswertung, showzus):
 * 1 = richtiges Ergebnis, 2 = richtige Tordifferenz, 3 = richtige Tendenz, 4 = richtige Toranzahl
 * einer Mannschaft, 5 = Bonus fuer ein richtiges Remis, 6 = Bonuspunkte durch den Joker.
 */
final class Punkte
{
    /**
     * @param int $tippmodus 1 = Ergebnisse tippen, 0 = Tendenz (tippmodus)
     * @param bool $entscheidungNachVerlaengerung nach Verlaengerung entschiedene Spiele als Remis werten (entscheidungnv)
     * @param bool $entscheidungImElfmeterschiessen im Elfmeterschiessen entschiedene Spiele als Remis werten (entscheidungie)
     * @param int|float $ergebnis Punkte fuer das richtige Ergebnis (rergebnis)
     * @param int|float $tendenzDifferenz Punkte fuer richtige Tendenz und Tordifferenz (rtendenzdiff)
     * @param int|float $tendenz Punkte fuer die richtige Tendenz (rtendenz)
     * @param int|float $tor Punkte fuer die richtige Toranzahl einer Mannschaft (rtor)
     * @param bool $tendenzUndTor Punkte fuer Tendenz und Toranzahl zusammen (rtendenztor)
     * @param bool $remisAlsTendenz ein richtiges Remis zaehlt als Tendenz statt als Tordifferenz (rtendenzremis)
     * @param int|float $remis Bonus fuer ein richtiges Remis (rremis)
     * @param int $gruenerTisch Entscheidungen am gruenen Tisch: 1 als Tendenz, 2 nicht werten, sonst als Tordifferenz (gtpunkte)
     */
    public function __construct(
        private int $tippmodus,
        private bool $entscheidungNachVerlaengerung,
        private bool $entscheidungImElfmeterschiessen,
        private int|float $ergebnis,
        private int|float $tendenzDifferenz,
        private int|float $tendenz,
        private int|float $tor,
        private bool $tendenzUndTor,
        private bool $remisAlsTendenz,
        private int|float $remis,
        private int $gruenerTisch,
    ) {
    }

    /**
     * Die Regeln aus der Konfiguration des Tippspiels (tipp/cfg.txt, ohne "tipp_" vor den Namen).
     *
     * @param array<string, mixed> $cfg
     */
    public static function ausKonfiguration(array $cfg): self
    {
        $zahl = static fn (string $name): int|float => is_numeric($cfg[$name] ?? null) ? +$cfg[$name] : 0;

        return new self(
            (int) $zahl('tippmodus'),
            $zahl('entscheidungnv') == 1,
            $zahl('entscheidungie') == 1,
            $zahl('rergebnis'),
            $zahl('rtendenzdiff'),
            $zahl('rtendenz'),
            $zahl('rtor'),
            $zahl('rtendenztor') == 1,
            $zahl('rtendenzremis') == 1,
            $zahl('rremis'),
            (int) $zahl('gtpunkte'),
        );
    }

    /**
     * Die Punkte fuer einen Tipp.
     *
     * @param int $tippHeim getippte Tore der Heimmannschaft
     * @param int $tippGast getippte Tore der Gastmannschaft
     * @param int $toreHeim Tore der Heimmannschaft
     * @param int $toreGast Tore der Gastmannschaft
     * @param int $wertung Entscheidung am gruenen Tisch: 0 keine, 1 fuer Heim, 2 fuer Gast, 3 beidseitiges Ergebnis
     * @param string $zusatz Zusatz zum Ergebnis (Spiel::zusatzText())
     * @param string $nachVerlaengerung Text fuer "nach Verlaengerung" (Sprachtext 0)
     * @param string $imElfmeterschiessen Text fuer "im Elfmeterschiessen" (Sprachtext 1)
     * @param int|float $jokerfaktor Faktor des Jokers, 1 ohne
     * @param bool $nichtWerten das Spiel zaehlt nicht fuer das Tippspiel
     * @return array{0:int|float, 1:array<int, int|float>} Punkte (-1 = nicht gewertet, -2 = Spiel nicht werten) und je Wertung (1 bis 6), was dazukommt
     */
    public function fuer(int $tippHeim, int $tippGast, int $toreHeim, int $toreGast, int $wertung, string $zusatz, string $nachVerlaengerung, string $imElfmeterschiessen, int|float $jokerfaktor, bool $nichtWerten): array
    {
        $gta0 = $tippHeim;
        $gtb0 = $tippGast;
        $ga0 = $toreHeim;
        $gb0 = $toreGast;
        /** @var array<int, int|float> $zaehler */
        $zaehler = [];

        if ($nichtWerten) {
            $punktespiel = -2;
        } // Spiel nicht werten
        elseif ($this->tippmodus == 1) {
            // Ergebnis-Tippmodus
            if ($wertung == 0) {
                if ($zusatz == $nachVerlaengerung && $this->entscheidungNachVerlaengerung) {
                    $punktespiel = $gtb0 == $gta0 ? $this->tendenzOderDifferenz($zaehler, $this->remisAlsTendenz) : 0;
                } elseif ($zusatz == $imElfmeterschiessen && $this->entscheidungImElfmeterschiessen) {
                    $punktespiel = $gtb0 == $gta0 ? $this->tendenzOderDifferenz($zaehler, $this->remisAlsTendenz) : 0;
                } elseif ($gta0 == $ga0 && $gtb0 == $gb0) {
                    $punktespiel = $this->ergebnis;
                    self::zaehle($zaehler, 1);
                } elseif ($ga0 == $gb0 && $gta0 == $gtb0 && $this->remisAlsTendenz) {
                    // richtiger 0-Tipp
                    $punktespiel = $this->tendenz;
                    self::zaehle($zaehler, 3);
                } elseif ($gtb0 - $gta0 == $gb0 - $ga0) {
                    $punktespiel = $this->tendenzDifferenz;
                    self::zaehle($zaehler, 2);
                } // richtige Tendenz und Tordiff
                elseif (($gtb0 > $gta0 && $gb0 > $ga0) || ($gtb0 < $gta0 && $gb0 < $ga0)) {
                    $punktespiel = $this->tendenz;
                    self::zaehle($zaehler, 3);
                    if ($this->tendenzUndTor && ($gta0 == $ga0 || $gtb0 == $gb0)) {
                        $punktespiel += $this->tor;
                        self::zaehle($zaehler, 4);
                    }
                } elseif ($gta0 == $ga0 || $gtb0 == $gb0) {
                    $punktespiel = $this->tor;
                    self::zaehle($zaehler, 4);
                } else {
                    $punktespiel = 0;
                }
            } elseif ($this->gruenerTisch == 2 && ($wertung == 1 || $wertung == 2 || $wertung == 3)) {
                // GT-Entscheidung nicht werten
                $punktespiel = -1;
            } elseif ($wertung == 1) {
                // GT-Entscheidung
                $punktespiel = $gtb0 - $gta0 < 0 ? $this->tendenzOderDifferenz($zaehler, $this->gruenerTisch == 1) : 0;
            } elseif ($wertung == 2) {
                // GT-Entscheidung
                $punktespiel = $gtb0 - $gta0 > 0 ? $this->tendenzOderDifferenz($zaehler, $this->gruenerTisch == 1) : 0;
            } elseif ($wertung == 3) {
                // GT-Entscheidung beidseitiges Erg.
                $punktespiel = $gtb0 - $gta0 == 0 ? $this->tendenzOderDifferenz($zaehler, $this->gruenerTisch == 1) : 0;
            } else {
                $punktespiel = -1;
            } // Ergebnis noch nicht eingetragen
        } elseif ($this->tippmodus == 0) {
            // Tendenz-Tippmodus
            if ($wertung == 0) {
                if ($zusatz == $nachVerlaengerung && $this->entscheidungNachVerlaengerung) {
                    $punktespiel = $gtb0 == $gta0 ? 1 : 0;
                } elseif ($zusatz == $imElfmeterschiessen && $this->entscheidungImElfmeterschiessen) {
                    $punktespiel = $gtb0 == $gta0 ? 1 : 0;
                } elseif (($gtb0 > $gta0 && $gb0 > $ga0) || ($gtb0 < $gta0 && $gb0 < $ga0) || ($gtb0 == $gta0 && $gb0 == $ga0)) {
                    $punktespiel = 1;
                } else {
                    $punktespiel = 0;
                }
            } elseif ($this->gruenerTisch == 2 && ($wertung == 1 || $wertung == 2 || $wertung == 3)) {
                // GT-Entscheidung nicht werten
                $punktespiel = -1;
            } elseif ($wertung == 1) {
                // GT-Entscheidung
                $punktespiel = $gtb0 - $gta0 < 0 ? 1 : 0;
            } elseif ($wertung == 2) {
                // GT-Entscheidung
                $punktespiel = $gtb0 - $gta0 > 0 ? 1 : 0;
            } elseif ($wertung == 3) {
                // GT-Entscheidung beidseitiges Erg.
                $punktespiel = $gtb0 - $gta0 == 0 ? 1 : 0;
            } else {
                $punktespiel = -1;
            }
        } else {
            // unbekannter Tippmodus: nicht gewertet (bis 4.5 blieben die Punkte ungesetzt)
            $punktespiel = -1;
        }
        if ($this->remis > 0 && $punktespiel > 0 && $gtb0 == $gta0 && $gb0 == $ga0) {
            $punktespiel += $this->remis;
            self::zaehle($zaehler, 5);
        }
        if ($jokerfaktor > 1 && $punktespiel > 0) {
            self::zaehle($zaehler, 6, $punktespiel * $jokerfaktor - $punktespiel);
            $punktespiel *= $jokerfaktor;
        }

        return [$punktespiel, $zaehler];
    }

    /**
     * Punkte fuer richtige Tendenz oder Tordifferenz (Remis nach Verlaengerung, gruener Tisch).
     *
     * @param array<int, int|float> $zaehler
     */
    private function tendenzOderDifferenz(array &$zaehler, bool $alsTendenz): int|float
    {
        if ($alsTendenz) {
            self::zaehle($zaehler, 3);

            return $this->tendenz;
        }
        self::zaehle($zaehler, 2);

        return $this->tendenzDifferenz;
    }

    /** @param array<int, int|float> $zaehler */
    private static function zaehle(array &$zaehler, int $nr, int|float $wert = 1): void
    {
        $zaehler[$nr] = ($zaehler[$nr] ?? 0) + $wert;
    }
}
