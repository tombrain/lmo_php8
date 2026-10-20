<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Lmo\Liga\Rohdaten\Liga;

/**
 * Die Tippuebersicht ueber alle Ligen eines Tippers ("Spiele der naechsten Tage"): sammelt aus
 * einer Liga die Spiele eines Zeitraums samt den Tipps des Tippers (bis 4.5
 * lmo-tippopenfileviewer.php) und schreibt die geaenderten Tipps zurueck in seine Tippdatei (bis
 * 4.5 lmo-tippsavefileviewer.php) und in die Einsicht des Spieltags
 * (lmo-tippsaveeinsichtviewer.php).
 *
 * Die Dateien schreibt sie wie bis 4.5, also anders als Tippdatei: ohne Leerzeilen, die Tipps in
 * den vorhandenen Spieltag einsortiert; in der Einsicht wandert der Abschnitt des Tippers ans Ende.
 */
final class Tippuebersicht
{
    /**
     * Die Spiele einer Liga, die im Zeitraum [$von, $bis) angepfiffen werden (ohne Anstosszeit am
     * ersten, sonst letzten Tag des Spieltags), mit den Tipps aus der Tippdatei.
     *
     * @param string $datei Ligadatei ohne .l98
     * @param array<int, string> $text Sprachtexte (0 n.V., 1 i.E.)
     * @param bool $ohneAnstoss Spiele ohne Anstosszeit zur Uhrzeit $standardzeit (tippohne)
     * @return list<UebersichtSpiel>
     */
    public static function sammle(Liga $liga, string $datei, string $tippdatei, int $von, int $bis, bool $ohneAnstoss, string $standardzeit, bool $jokertipp, Tippzeit $tippzeit, array $text): array
    {
        $e = $liga->einstellungen();
        $pokal = $liga->istPokal();
        // die Zeilen der Tippdatei als [Abschnitt, Schluessel, Wert, Joker]; der Joker gilt bis zur naechsten @-Zeile
        $tipps = [];
        $abschnitt = '';
        $joker = '';
        foreach (is_readable($tippdatei) ? (file($tippdatei) ?: []) : [] as $zeile) {
            $zeile = trim($zeile);
            if (str_starts_with($zeile, '@') && str_ends_with($zeile, '@')) {
                $joker = trim(substr($zeile, 1, -1));
            } elseif (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                $abschnitt = trim(substr($zeile, 1, -1));
            } elseif (str_contains($zeile, '=') && !str_starts_with($zeile, ';')) {
                $tipps[] = [$abschnitt, trim(substr($zeile, 0, (int) strpos($zeile, '='))), trim(substr($zeile, (int) strpos($zeile, '=') + 1)), $joker];
            }
        }

        $spiele = [];
        $runden = strlen(decbin($e->mannschaften() - 1));
        foreach ($liga->spieltage as $nr => $spieltag) {
            $st = (string) $nr;
            // Datum des Spieltags in der Form TT.MM.JJJJ und als Zeit; ein Datum, das es nicht gibt, zaehlt als leer
            $tage = ['', ''];
            $zeiten = [0, 0];
            foreach ([$spieltag->ersterTag(), $spieltag->letzterTag()] as $t => $zeit) {
                if ($zeit !== null) {
                    $tage[$t] = date('d.m.Y', $zeit);
                    if ($ohneAnstoss && $standardzeit > 0) {
                        $zeit = $zeit + (int) substr($standardzeit, 0, 2) * 60 * 60 + (int) substr($standardzeit, 3, 2) * 60;
                    }
                    $zeiten[$t] = $zeit;
                }
            }
            // Spiele je Paarung: in der Liga eines, im Pokal nach dem Modus der Runde
            $modus = $pokal ? ($spieltag->werte['MO'] ?? '1') : 1;
            foreach ($spieltag->partien as $partieNr => $partie) {
                foreach ($partie->spiele as $spielNr => $werte) {
                    // jedes Spiel mit seinen eigenen Werten; ohne Mannschaft oder ohne Anstosszeile (AT) ist es keines
                    if (!($partie->heim > 0) || !isset($werte['AT'])) {
                        continue;
                    }
                    $anstoss = $werte['AT'];
                    $zeit = $anstoss > 0 ? (int) $anstoss : ($zeiten[0] > 0 ? $zeiten[0] : $zeiten[1]);
                    if (!($zeit > 0 && $von <= $zeit && $bis > $zeit)) {
                        continue;
                    }
                    $nummer = $pokal ? $partieNr . $spielNr : (string) $partieNr;
                    $spiele[] = self::spiel($liga, $datei, $st, $nummer, $partie->heim, $partie->gast ?? 0, $werte, $tage, $runden, $modus, $text, $pokal);
                    self::mitTipp($spiele[count($spiele) - 1], $tipps, $jokertipp, $spieltag, $tage, $tippzeit, $pokal);
                }
            }
        }

        return $spiele;
    }

    /**
     * Das Spiel zum Anzeigen; die Lieblingsmannschaft der Liga ist hervorgehoben.
     *
     * @param array<string, string> $werte
     * @param array{0: string, 1: string} $tage
     * @param array<int, string> $text
     */
    private static function spiel(Liga $liga, string $datei, string $st, string $nummer, string $heim, string|int $gast, array $werte, array $tage, int $runden, string|int $modus, array $text, bool $pokal): UebersichtSpiel
    {
        $e = $liga->einstellungen();
        $namen = [];
        foreach ([$heim, $gast] as $nr) {
            $name = $nr > 0 ? $liga->mannschaftsname($nr) : '___';
            $namen[] = $e->lieblingsmannschaft() == $nr ? '<strong>' . $name . '</strong>' : $name;
        }
        $wertung = (!$pokal && ($werte['ET'] ?? '') == 3) ? 3 : 0;
        $tore = [];
        foreach (['GA' => 1, 'GB' => 2] as $schluessel => $sieger) {
            $wert = $werte[$schluessel] ?? '';
            if ($wert == '') {
                $wert = -1;
            }
            if ($wert == '-1') {
                $wert = '_';
            }
            if ($wert == '-2') {
                // am gruenen Tisch gewonnen
                $wertung = $sieger;
                $wert = 0;
            }
            $tore[] = $wert;
        }
        $zusatz = $werte['SP'] ?? 0;
        if ($zusatz == 0) {
            $zusatz = '&nbsp;';
        }
        if ($zusatz == 2) {
            $zusatz = $text[0] ?? '';
        }
        if ($zusatz == 1) {
            $zusatz = $text[1] ?? '';
        }

        $spiel = new AnstehendesSpiel();
        $spiel->titel = $e->name();
        $typ = $e->wert('Type');
        $spiel->typ = is_int($typ) || is_string($typ) ? $typ : 0;
        $spiel->runden = $runden;
        $spiel->ohneUnentschieden = $e->ohneUnentschieden();
        $spiel->mitSpieltagsdatum = $e->mitSpieltagsdatum();
        $spiel->mitAnstosszeit = $e->mitAnstosszeit();
        $spiel->mitSpielbericht = $e->mitSpielberichten();
        $spiel->mitZusatz = $e->mitVerlaengerung();
        $spiel->torfaktor = $e->torfaktor();
        $spiel->spieleJePaarung = $modus;
        $spiel->ersterTag = $tage[0];
        $spiel->letzterTag = $tage[1];
        $spiel->heim = $namen[0];
        $spiel->gast = $namen[1];
        $spiel->toreHeim = $tore[0];
        $spiel->toreGast = $tore[1];
        $spiel->zusatz = $zusatz;
        $spiel->wertung = $wertung;
        $spiel->notiz = addslashes($werte['NT'] ?? '');
        $spiel->bericht = addslashes($werte['BE'] ?? '');
        $spiel->ohneTippwertung = $werte['TI'] ?? 0;
        $spiel->anstoss = $werte['AT'] ?? 0;

        return new UebersichtSpiel($spiel, $datei, $st, $nummer, '', '', '', '0');
    }

    /**
     * Der Tipp und Joker des Tippers zu einem Spiel aus den Zeilen seiner Tippdatei; wie bis 4.5
     * gehoert zu einer GB-Zeile die letzte GA-Zeile davor.
     *
     * @param list<array{0: string, 1: string, 2: string, 3: string}> $tipps
     * @param array{0: string, 1: string} $tage
     */
    private static function mitTipp(UebersichtSpiel $s, array $tipps, bool $jokertipp, \Lmo\Liga\Rohdaten\Spieltag $spieltag, array $tage, Tippzeit $tippzeit, bool $pokal): void
    {
        $heim = '';
        foreach ($tipps as [$abschnitt, $schluessel, $wert, $joker]) {
            $art = substr($schluessel, 0, 2);
            if ($art == 'GA') {
                $heim = $wert;
            } elseif ($art == 'GB') {
                $nummer = substr($schluessel, 2);
                if (substr($abschnitt, 5) != $s->spieltag || $nummer != $s->nr) {
                    continue;
                }
                $s->tippa = $heim == '' || $heim == '-1' ? '_' : $heim;
                $s->tippb = $wert == '' || $wert == '-1' ? '_' : $wert;
                if ($jokertipp) {
                    $s->joker = $joker == $nummer ? $nummer : 0;
                    if ($joker > 0) {
                        // Anstoss des Jokerspiels in diesem Spieltag
                        foreach ($spieltag->partien as $partieNr => $partie) {
                            foreach ($partie->spiele as $spielNr => $werte) {
                                if (isset($werte['AT']) && ($pokal ? $partieNr . $spielNr : (string) $partieNr) == $joker) {
                                    $s->jokerBis = $tippzeit->bis((int) $werte['AT'], $tage[0], $tage[1]) ?? '';
                                    break 2;
                                }
                            }
                        }
                    }
                }

                return;
            }
        }
    }

    /**
     * Schreibt die Tipps der Spiele einer Liga in die Tippdatei. false, wenn das nicht ging.
     *
     * @param list<UebersichtSpiel> $spiele die Spiele dieser Liga
     */
    public static function speichereTippdatei(string $pfad, array $spiele): bool
    {
        $daten = self::zeilen($pfad);
        $inhalt = '';
        $gespeichert = [];
        $runde = 0;
        for ($l = 0; $l < count($daten); $l++) {
            $zeile = $daten[$l];
            if (substr($zeile, 0, 6) == '[Round') {
                $inhalt .= $zeile . "\n";
                $runde = substr($zeile, 6, -1);
                $mitJoker = false;
                $gespeichert[$runde] = true;
                foreach ($spiele as $s) {
                    if ($runde == $s->spieltag) {
                        // getippte dazu schreiben
                        if (!$mitJoker) {
                            [$jokerZeile, $l] = self::joker($s, $daten, $l);
                            $inhalt .= $jokerZeile;
                            $mitJoker = $jokerZeile != '';
                        }
                        $inhalt .= self::tipp($s);
                    }
                }
                if (!$mitJoker && str_starts_with($daten[$l + 1] ?? '', '@')) {
                    // Joker von nicht getippten Spieltag zurueckschreiben
                    $l++;
                    $inhalt .= $daten[$l] . "\n";
                }
            } elseif ($zeile != '' && !str_starts_with($zeile, '@')) {
                $nummer = substr($zeile, 2, (int) strpos($zeile, '=') - 2);
                $neu = false;
                foreach ($spiele as $s) {
                    if ($nummer == $s->nr && $runde == $s->spieltag) {
                        // nicht zurueckschreiben
                        $neu = true;
                        break;
                    }
                }
                if (!$neu) {
                    $inhalt .= $zeile . "\n";
                }
            }
        }
        foreach ($spiele as $k => $s) {
            if ($s->spieltag > 0 && !isset($gespeichert[$s->spieltag])) {
                // vorher nicht getippte Spieltage dazu schreiben
                if ($k == 0 || $s->spieltag != $spiele[$k - 1]->spieltag) {
                    $inhalt .= '[Round' . $s->spieltag . "]\n" . ($s->joker > 0 ? '@' . $s->joker . "@\n" : '');
                }
                $inhalt .= self::tipp($s);
            }
        }

        return @file_put_contents($pfad, $inhalt, LOCK_EX) !== false;
    }

    /**
     * Schreibt die Tipps der Spiele eines Spieltags in dessen Einsicht: die Abschnitte der anderen
     * Tipper bleiben, der des Tippers kommt ans Ende. false, wenn das nicht ging.
     *
     * @param list<UebersichtSpiel> $spiele die Spiele dieses Spieltags
     */
    public static function speichereEinsicht(string $pfad, string $nick, array $spiele): bool
    {
        $daten = self::zeilen($pfad);
        $inhalt = '';
        $abschnitt = '';
        $von = 0;
        $bis = -1;
        foreach ($daten as $l => $zeile) {
            if (str_starts_with($zeile, '[') && str_ends_with($zeile, ']')) {
                $abschnitt = substr($zeile, 1, -1);
                if ($abschnitt == $nick) {
                    $von = $l;
                    $bis = $l;
                }
            }
            if ($abschnitt != $nick) {
                // nur die unveraenderten Tipps werden zurueckgeschrieben (auch die Leerzeile am Anfang)
                $inhalt .= $zeile . "\n";
            } elseif ($zeile != '') {
                $bis = $l;
            }
        }
        for ($l = $von; $l <= $bis; $l++) {
            $zeile = $daten[$l];
            if (str_starts_with($zeile, '[')) {
                // am Ende getippte dazu schreiben
                $inhalt .= $zeile . "\n";
                $mitJoker = false;
                foreach ($spiele as $s) {
                    if (!$mitJoker) {
                        [$jokerZeile, $l] = self::joker($s, $daten, $l);
                        $inhalt .= $jokerZeile;
                        $mitJoker = $jokerZeile != '';
                    }
                    $inhalt .= self::tipp($s);
                }
            } elseif ($zeile != '' && !str_starts_with($zeile, '@')) {
                $nummer = substr($zeile, 2, (int) strpos($zeile, '=') - 2);
                $neu = false;
                foreach ($spiele as $s) {
                    if ($nummer == $s->nr) {
                        // nicht zurueckschreiben
                        $neu = true;
                        break;
                    }
                }
                if (!$neu) {
                    $inhalt .= $zeile . "\n";
                }
            }
        }
        if ($bis == -1 && $spiele !== []) {
            // keine bisherigen Tipps vom Tipper
            $inhalt .= '[' . $nick . "]\n" . ($spiele[0]->joker > 0 ? '@' . $spiele[0]->joker . "@\n" : '');
            foreach ($spiele as $s) {
                $inhalt .= self::tipp($s);
            }
        }

        return @file_put_contents($pfad, $inhalt, LOCK_EX) !== false;
    }

    /**
     * Die Joker-Zeile zu einem Spiel: sein Joker, sonst die @-Zeile, die in der Datei folgt (dann
     * ist sie verbraucht und $l steht auf ihr); '' wenn es keine gibt.
     *
     * @param list<string> $daten
     * @return array{0: string, 1: int}
     */
    private static function joker(UebersichtSpiel $s, array $daten, int $l): array
    {
        if ($s->joker > 0) {
            return ['@' . $s->joker . "@\n", $l];
        }
        if (str_starts_with($daten[$l + 1] ?? '', '@')) {
            return [$daten[$l + 1] . "\n", $l + 1];
        }

        return ['', $l];
    }

    /** Die beiden Zeilen eines Tipps; ohne Tipp -1. */
    private static function tipp(UebersichtSpiel $s): string
    {
        $wert = static fn (int|string $tore): string => $tore === '_' || $tore === '' ? '-1' : (string) $tore;

        return 'GA' . $s->nr . '=' . $wert($s->tippa) . "\n" . 'GB' . $s->nr . '=' . $wert($s->tippb) . "\n";
    }

    /**
     * Die Zeilen einer Datei ohne Zeilenende und ohne leere, wie bis 4.5 mit einer leeren davor.
     *
     * @return list<string>
     */
    private static function zeilen(string $pfad): array
    {
        $daten = [''];
        foreach (is_readable($pfad) ? (file($pfad) ?: []) : [] as $zeile) {
            $zeile = trim($zeile);
            if ($zeile != '') {
                $daten[] = $zeile;
            }
        }

        return $daten;
    }
}
