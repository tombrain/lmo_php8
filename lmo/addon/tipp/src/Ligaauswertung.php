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

namespace Lmo\Addon\Tipp;

use Lmo\Liga\Rohdaten\Liga;
use Lmo\Liga\Spiel;

/**
 * Die Auswertung der Tipps einer Liga: je Tipper die getippten Spiele (SG<Spieltag>), die Punkte
 * (TP<Spieltag>) und mit Zusatzauswertung (showzus) wofuer es sie gab (P1 bis P6), in
 * auswert/<liga>.aus; mit Wertung nach Vereinen (wertverein, nicht im Pokal) dazu je Tipper
 * auswert/vereine/<liga>_<nick>.ver mit Punkten und Spielen je Mannschaft. Bis 4.5
 * lmo-tippsavewert.php mit lmo-tippopenfile.php, lmo-tippopenfileall.php und lmo-tippcalcpkt.php.
 *
 * Ausgewertet wird ein Spieltag oder alle (0); bei einem Spieltag bleiben die uebrigen Werte aus
 * der alten Datei. Mit von/bis nur ein Teil der Tipper, gezaehlt nach ihren Tippdateien; die
 * anderen bleiben, wie sie waren. Im Pokal zaehlt nur die Auswertung einer einzelnen Runde.
 */
final class Ligaauswertung
{
    /**
     * @param string $tippordner Ordner der Tippdateien mit / am Ende (daten/tipp/<dirtipp>)
     * @param string $tipperdatei die Tipperdatei (Team, Name, E-Mail)
     * @param array<string, string> $cfg Einstellungen des Tippspiels (tipp/cfg.txt)
     * @param string $nachVerlaengerung Sprachtext 0
     * @param string $imElfmeterschiessen Sprachtext 1
     */
    public function __construct(
        private string $tippordner,
        private string $tipperdatei,
        private array $cfg,
        private string $nachVerlaengerung,
        private string $imElfmeterschiessen,
    ) {
    }

    /**
     * Wertet die Liga aus und schreibt ihre Dateien. Ohne Liga (die Datei fehlt) bleibt die
     * Auswertungsdatei leer, wie bis 4.5.
     *
     * @param string $liga Name der Liga (Dateiname ohne .l98)
     * @param int $st der Spieltag, 0 fuer alle
     * @param int|null $von erster ausgewerteter Tipper (ab 1, nach Tippdateien); null: der erste
     * @param int|null $bis letzter ausgewerteter Tipper; null: der letzte
     * @return bool false, wenn die Auswertungsdatei nicht geschrieben werden konnte
     */
    public function werte(string $liga, ?Liga $rohdaten, int $st, ?int $von = null, ?int $bis = null): bool
    {
        $auswertfile = $this->tippordner . 'auswert/' . $liga . '.aus';
        $alt = $this->alteDaten($auswertfile, $liga);
        $aus = '';
        if ($rohdaten !== null) {
            $aus = $this->tipper($liga, $rohdaten, $st, $alt, $von, $bis);
        }

        return @file_put_contents($auswertfile, $aus, LOCK_EX) !== false;
    }

    /**
     * Die Zeilen der alten Auswertung, je Tipper mit seinem Abschnitt; Tipper ohne Tippdatei fallen weg.
     *
     * @return list<string> beginnt mit '' (wie bis 4.5)
     */
    private function alteDaten(string $datei, string $liga): array
    {
        $alt = [''];
        if (!is_file($datei)) {
            return $alt;
        }
        $nick = '';
        foreach (file($datei) ?: [] as $zeile) {
            $zeile = trim($zeile);
            if ($zeile == '') {
                continue;
            }
            if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                $nick = substr($zeile, 1, -1);
                if (!file_exists($this->tippordner . $liga . '_' . $nick . '.tip')) {
                    $nick = '';
                }
            }
            if ($nick != '') {
                $alt[] = $zeile;
            }
        }

        return $alt;
    }

    /** @param list<string> $alt */
    private function tipper(string $liga, Liga $rohdaten, int $st, array $alt, ?int $von, ?int $bis): string
    {
        $cfg = $this->cfg;
        $einstellungen = $rohdaten->einstellungen();
        $pokal = $einstellungen->istPokal();
        $spieltage = $einstellungen->spieltage();
        $mannschaften = $einstellungen->mannschaften();
        $mitVereinen = !$pokal && ($cfg['wertverein'] ?? '') == 1;
        $zusatz = ($cfg['showzus'] ?? '') == 1;
        $ergebnisModus = ($cfg['tippmodus'] ?? '') == 1;
        $joker = ($cfg['jokertipp'] ?? '') == 1;
        $jokerfaktor = is_numeric($cfg['jokertippmulti'] ?? '') ? +$cfg['jokertippmulti'] : 1;
        $punkte = Punkte::ausKonfiguration($cfg);
        $tipperdatei = Tipperdatei::lies($this->tipperdatei);
        $runden = [];

        // die Tippdateien der Liga, in fester Reihenfolge
        $dateien = [];
        foreach (is_dir($this->tippordner) ? (scandir($this->tippordner) ?: []) : [] as $datei) {
            $unterstrich = strrpos($datei, '_');
            if ($unterstrich !== false && strtolower(substr($datei, 0, $unterstrich)) == strtolower($liga) && strtolower(substr($datei, -4)) == '.tip') {
                $dateien[] = $datei;
            }
        }
        sort($dateien);
        $von ??= 1;
        $bis ??= count($dateien);

        $aus = '';
        foreach ($dateien as $l => $datei) {
            $nick = substr($datei, (int) strrpos($datei, '_') + 1, -4);
            if ($l < $von - 1 || $l > $bis - 1) {
                // nicht ausgewertete Tipper: wie sie waren
                $abschnitt = '';
                foreach ($alt as $zeile) {
                    if ($zeile != '') {
                        if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                            $abschnitt = substr($zeile, 1, -1);
                        }
                        if ($abschnitt == $nick) {
                            $aus .= $zeile . "\n";
                        }
                    }
                }
                continue;
            }
            $tipper = $tipperdatei->tipper($nick);
            if ($tipper === null) {
                continue;
            }
            $aus .= "\n[" . $tipper->feld(0) . "]\n" . 'Team=' . $tipper->feld(5) . "\n";
            if (($cfg['showname'] ?? '') == 1) {
                $aus .= 'Name=' . $tipper->feld(3) . "\n";
            }
            if (($cfg['showemail'] ?? '') == 1) {
                $aus .= 'Email=' . $tipper->feld(4) . "\n";
            }
            $tippdatei = Tippdatei::lies($this->tippordner . $datei);
            $alle = $st == 0 ? $tippdatei->alleRundenMitJoker() : null;

            /** @var array<int, array<int, int|float>> $vpunkte Mannschaft => Spieltag => Punkte */
            $vpunkte = [];
            /** @var array<int, array<int, int>> $vspiele Mannschaft => Spieltag => Spiele */
            $vspiele = [];
            foreach ($st > 0 ? [$st] : range(1, max(1, $spieltage)) as $st1) {
                if ($st == 0 && $st1 > $spieltage) {
                    break;
                }
                $runde = $runden[$st1] ??= Tipprunde::fuer($rohdaten, $st1);
                if ($st > 0) {
                    $gelesen = $tippdatei->runde($st, (int) $runde->plaetze, $runde->pokal ? $runde->spieleJePaarung : null);
                    $tipps = $gelesen['tipps'];
                    $jokerspiel = $gelesen['joker'];
                } else {
                    $tipps = $alle['tipps'][$st1 - 1] ?? [];
                    $jokerspiel = $alle['joker'][$st1 - 1] ?? '';
                }
                // im Pokal zaehlt nur die Auswertung einer einzelnen Runde
                $spieleJePaarung = $st == 0 ? 0 : $runde->spieleJePaarung;
                $getippt = 0;
                $tp = 0;
                $zaehler = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
                for ($i = 0; $i < $runde->plaetze; $i++) {
                    $paarung = $runde->paarungen[$i] ?? null;
                    if ($paarung === null) {
                        continue;
                    }
                    $spiele = $pokal ? range(0, $spieleJePaarung - 1) : [0];
                    if ($pokal && $spieleJePaarung == 0) {
                        $spiele = [];
                    }
                    foreach ($spiele as $n) {
                        $spiel = $paarung->spiele[$n] ?? null;
                        if ($spiel === null) {
                            continue;
                        }
                        $tipp = $pokal ? Tippdatei::tippAus($tipps, $i, $n) : Tippdatei::tippAus($tipps, $i);
                        $nummer = $pokal ? ($i + 1) . ($n + 1) : (string) ($i + 1);
                        $faktor = $joker && $jokerspiel == $nummer ? $jokerfaktor : 1;
                        $wert = -1;
                        if ($tipp !== null && $tipp[0] != Tippdatei::KEIN_TIPP && $spiel->toreHeim !== null && ($st == 0 || $spiel->toreHeim > -1)) {
                            [$wert, $dazu] = $punkte->fuer((int) $tipp[0], (int) $tipp[1], (int) $spiel->toreHeimText(), (int) $spiel->toreGastText(),
                                $pokal ? 0 : $spiel->wertung, $spiel->zusatzText($this->nachVerlaengerung, $this->imElfmeterschiessen),
                                $this->nachVerlaengerung, $this->imElfmeterschiessen, $faktor, $spiel->ohneTippwertung);
                            if ($zusatz) {
                                foreach ($dazu as $nr => $z) {
                                    $zaehler[$nr] += $z;
                                }
                            }
                        }
                        if ($wert > -1) {
                            $getippt++;
                        }
                        if ($wert > 0) {
                            $tp += $wert;
                        }
                        if ($mitVereinen) {
                            self::zaehleVerein($vpunkte, $vspiele, $spiel, $st1, $wert);
                        }
                    }
                }
                if ($getippt > 0) {
                    $aus .= 'SG' . $st1 . '=' . $getippt . "\n";
                }
                if ($zusatz) {
                    foreach ($zaehler as $nr => $z) {
                        if (($nr > 4 || $ergebnisModus) && $z > 0) {
                            $aus .= 'P' . $nr . $st1 . '=' . $z . "\n";
                        }
                    }
                }
                if ($tp > 0) {
                    $aus .= 'TP' . $st1 . '=' . $tp . "\n";
                }
            }
            if ($st > 0) {
                $aus .= self::uebrigeSpieltage($alt, $nick, $st, true);
            }
            if ($mitVereinen) {
                $this->schreibeVereine($liga, $nick, $mannschaften, $spieltage, $st, $vpunkte, $vspiele);
            }
        }

        return $aus;
    }

    /**
     * @param array<int, array<int, int|float>> $vpunkte
     * @param array<int, array<int, int>> $vspiele
     */
    private static function zaehleVerein(array &$vpunkte, array &$vspiele, Spiel $spiel, int $st1, int|float $wert): void
    {
        foreach ([$spiel->heim, $spiel->gast] as $mannschaft) {
            if ($wert > 0) {
                $vpunkte[$mannschaft][$st1] = ($vpunkte[$mannschaft][$st1] ?? 0) + $wert;
            }
            if ($wert > -1) {
                $vspiele[$mannschaft][$st1] = ($vspiele[$mannschaft][$st1] ?? 0) + 1;
            }
        }
    }

    /**
     * Aus der alten Datei die Zeilen eines Abschnitts (Tipper bzw. Mannschaft) ausser denen des
     * ausgewerteten Spieltags; beim Tipper ohne Team, Name und Email.
     *
     * @param list<string> $alt
     */
    private static function uebrigeSpieltage(array $alt, string $abschnitt, int $st, bool $ohneKopf): string
    {
        $aus = '';
        $dat = 0;
        while ($abschnitt != substr($alt[$dat], 1, -1) && $dat < count($alt) - 1) {
            $dat++;
        }
        $name = substr($alt[$dat], 1, -1);
        if ($name != $abschnitt) {
            return '';
        }
        for ($dat++; $dat < count($alt); $dat++) {
            $zeile = $alt[$dat];
            if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                break;
            }
            $kopf = str_starts_with($zeile, 'Team') || str_starts_with($zeile, 'Name') || str_starts_with($zeile, 'Email');
            if (substr($zeile, 2, (int) strpos($zeile, '=') - 2) != $st && !($ohneKopf && $kopf)) {
                $aus .= $zeile . "\n";
            }
        }

        return $aus;
    }

    /**
     * Die Wertung nach Vereinen eines Tippers: je Mannschaft seine Punkte und Spiele je Spieltag.
     *
     * @param array<int, array<int, int|float>> $vpunkte
     * @param array<int, array<int, int>> $vspiele
     */
    private function schreibeVereine(string $liga, string $nick, int $mannschaften, int $spieltage, int $st, array $vpunkte, array $vspiele): void
    {
        $datei = $this->tippordner . 'auswert/vereine/' . $liga . '_' . $nick . '.ver';
        $alt = [''];
        if ($st > 0 && is_file($datei)) {
            $verein = '';
            foreach (file($datei) ?: [] as $zeile) {
                $zeile = trim($zeile);
                if ($zeile == '') {
                    continue;
                }
                if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                    $verein = substr($zeile, 1, -1);
                }
                if ($verein != '') {
                    $alt[] = $zeile;
                }
            }
        }
        $aus = '';
        for ($i = 1; $i <= $mannschaften; $i++) {
            $aus .= '[' . $i . "]\n";
            foreach ($st > 0 ? [$st] : range(1, max(1, $spieltage)) as $j) {
                if ($st == 0 && $j > $spieltage) {
                    break;
                }
                if ($st > 0) {
                    $aus .= self::uebrigeSpieltage($alt, (string) $i, $st, false);
                }
                if (($vpunkte[$i][$j] ?? 0) > 0) {
                    $aus .= 'TP' . $j . '=' . $vpunkte[$i][$j] . "\n";
                }
                if (($vspiele[$i][$j] ?? 0) > 0) {
                    $aus .= 'SG' . $j . '=' . $vspiele[$i][$j] . "\n";
                }
            }
        }
        if (is_dir(dirname($datei))) {
            file_put_contents($datei, $aus, LOCK_EX);
        }
    }
}
