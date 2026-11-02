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

use Closure;
use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Controller\KalenderController;

/**
 * Mails an die Tipper aus der Administration (Newsletter/Reminder): ein Newsletter an alle, die ihn
 * wollen (Feld 9), eine Mail an einen Tipper, und die Erinnerung an die Spiele der naechsten Tage,
 * die sie noch nicht getippt haben (Feld 10), fuer eine Liga oder alle Ligen zum Tippen. Im Text
 * stehen fuer [nick], [name], [pass] (ein Link zum neuen Kennwort) und [spiele] die Werte des
 * Tippers. Bis 4.5 lmo-tippemail.php mit lmo-tippemailviewer.php, lmo-tippemailviewer1.php,
 * lmo-tippopenfile.php und lmo-tippopenfileall.php.
 *
 * Die Tipper zaehlen wie bisher in der Reihenfolge der Tipperdatei, von und bis ab 1.
 */
final class Rundmail
{
    /** @var list<string> die Zeilen der Tipperdatei ohne die Schutzzeile */
    private array $zeilen = [];

    /**
     * @param string $tippordner Ordner der Tippdateien mit / am Ende (daten/tipp/<dirtipp>)
     * @param string $ligenordner Ligenordner mit / am Ende
     * @param array<string, string> $cfg Einstellungen des Tippspiels (tipp/cfg.txt)
     * @param array<int|string, string> $text Sprachtexte
     * @param array<int|string, string> $t Sprachtexte des Tippspiels
     * @param array<int, string> $datumsnamen Wochentage und Monate (Kontext::texteVon('date'))
     * @param string $kennwortAdresse Adresse fuer [pass], der Nick kommt dahinter
     * @param Closure(string, string): ?string $versende schickt den Text an die Adresse; null oder der Fehler
     */
    public function __construct(
        string $tipperdatei,
        private string $tippordner,
        private string $ligenordner,
        private array $cfg,
        private Tippzeit $tippzeit,
        private array $text,
        private array $t,
        private array $datumsnamen,
        private string $kennwortAdresse,
        private Closure $versende,
        private int $jetzt,
    ) {
        foreach (is_file($tipperdatei) ? (file($tipperdatei) ?: []) : [] as $zeile) {
            if (!str_starts_with(trim($zeile), '<?php')) {
                $this->zeilen[] = $zeile;
            }
        }
    }

    /**
     * Der Newsletter an die Tipper von bis, die ihn wollen und eine E-Mail-Adresse haben.
     *
     * @return array{int, string} verschickte Mails und die Fehler
     */
    public function newsletter(string $nachricht, int $von, int $bis): array
    {
        $anzahl = 0;
        $fehler = '';
        for ($nr = $von - 1; $nr < $bis; $nr++) {
            $felder = explode('|', $this->zeilen[$nr] ?? '');
            if (isset($felder[9], $felder[4]) && $felder[9] != -1 && $felder[4] != '') {
                $this->schicke($felder, $this->ersetze($nachricht, $felder), $anzahl, $fehler);
            }
        }

        return [$anzahl, $fehler];
    }

    /**
     * Eine Mail an einen Tipper. Wie bis 4.5: Gibt es den Nick nicht, bekommt sie der letzte Tipper
     * der Datei.
     *
     * @return array{int, string} verschickte Mails und die Fehler
     */
    public function einzeln(string $nachricht, string $nick): array
    {
        $anzahl = 0;
        $fehler = '';
        $felder = explode('|', $this->zeilen[0] ?? '');
        for ($i = 0; $i < count($this->zeilen) && $nick != $felder[0]; $i++) {
            $felder = explode('|', $this->zeilen[$i]);
        }
        $this->schicke($felder, $this->ersetze($nachricht, $felder), $anzahl, $fehler);

        return [$anzahl, $fehler];
    }

    /**
     * Die Erinnerung an die Spiele der naechsten Tage, die die Tipper von bis noch nicht getippt
     * haben: fuer eine Liga (Datei im Ligenordner, ein Spieltag oder 0 fuer alle) oder, mit $liga
     * null, fuer alle Ligen zum Tippen.
     *
     * @return array{int, string} verschickte Mails und die Fehler
     */
    public function erinnerung(string $nachricht, string $tage, int $von, int $bis, ?string $liga, string $st): array
    {
        $bisZeit = (int) strtotime('+' . $tage . ' day', $this->jetzt);
        if ($liga === null) {
            return $this->erinnerungAllerLigen($nachricht, $bisZeit, $von, $bis);
        }
        $anzahl = 0;
        $fehler = '';
        if ($liga != '' && $tage > 0 && $st >= 0) {
            $rohdaten = Liga::lies($this->ligenordner . $liga);
            for ($nr = $von - 1; $nr < $bis; $nr++) {
                $felder = explode('|', $this->zeilen[$nr] ?? '');
                if (!isset($felder[10], $felder[4]) || $felder[10] == -1 || $felder[4] == '') {
                    continue;
                }
                $tippdatei = $this->tippordner . substr($liga, 0, -4) . '_' . $felder[0] . '.tip';
                if (!file_exists($tippdatei)) {
                    continue;
                }
                $spiele = $this->offeneSpiele($rohdaten, Tippdatei::lies($tippdatei), (int) $st, $bisZeit);
                if ($spiele != '') {
                    $this->schicke($felder, str_replace('[spiele]', $spiele, $this->ersetze($nachricht, $felder)), $anzahl, $fehler);
                }
            }
        }

        return [$anzahl, $fehler];
    }

    /** Die noch nicht getippten Spiele einer Liga bis $bisZeit, je Zeile eines. */
    private function offeneSpiele(Liga $liga, Tippdatei $tippdatei, int $st, int $bisZeit): string
    {
        $einstellungen = $liga->einstellungen();
        $pokal = $einstellungen->istPokal();
        $imVoraus = (int) ($this->cfg['imvorraus'] ?? -1);
        if ($st > 0) {
            $runde = Tipprunde::fuer($liga, $st);
            $tipps = $tippdatei->runde($st, (int) $runde->plaetze, $runde->pokal ? $runde->spieleJePaarung : null)['tipps'];
            $erste = $st - 1;
            $letzte = $st - 1;
            $paarungen = $runde->plaetze;
        } else {
            $tipps = self::alleTipps($tippdatei, $pokal);
            $erste = 0;
            $letzte = $einstellungen->spieltage() - 1;
            $paarungen = $einstellungen->partienJeSpieltag();
        }
        $spiele = '';
        for ($st0 = $erste; $st0 <= $letzte; $st0++) {
            if (!($imVoraus < 0 || $st0 <= $einstellungen->aktuellerSpieltag() + $imVoraus)) {
                continue;
            }
            $runde = Tipprunde::fuer($liga, $st0 + 1);
            for ($dd = 0; $dd < $paarungen; $dd++) {
                $paarung = $runde->paarungen[$dd] ?? null;
                if ($paarung === null) {
                    continue;
                }
                foreach ($paarung->spiele as $ddd => $spiel) {
                    $zeit = $this->tippzeit->bis((int) $spiel->anstoss, $runde->ersterTag, $runde->letzterTag);
                    if ($zeit === null || !($this->jetzt < $zeit && $bisZeit > $zeit)) {
                        continue;
                    }
                    if ($st > 0) {
                        $tipp = $pokal ? ($tipps[$dd][$ddd][0] ?? null) : ($tipps[$dd][0] ?? Tippdatei::KEIN_TIPP);
                    } else {
                        $tipp = $pokal ? self::tippAusAllen($tipps, $st0, $dd, $ddd, 7, 64, 7) : self::tippAusAllen($tipps, $st0, $dd, null, 116, 40, 0);
                    }
                    if ($tipp == Tippdatei::KEIN_TIPP && $paarung->heim > 0) {
                        $spiele .= ($spiele != '' ? "\n" : '') . $paarung->heimMannschaft()->anzeigename() . ' - ' . $paarung->gastMannschaft()->anzeigename()
                            . ' (' . ($this->t[87] ?? '') . ' ' . KalenderController::uebersetzt(date('l, d.F.Y H:i', $zeit), $this->datumsnamen) . ')';
                    }
                }
            }
        }

        return $spiele;
    }

    /**
     * Die Tipps auf die Heimmannschaft aller Spieltage, wie bis 4.5 lmo-tippopenfileall.php: Spieltag
     * (ab 0) => Paarung (ab 0) bzw. im Pokal => Paarung => Spiel; ein leerer Tipp oder -1 ist KEIN_TIPP.
     *
     * @return array<int, array<int, mixed>>
     */
    private static function alleTipps(Tippdatei $tippdatei, bool $pokal): array
    {
        $tipps = [];
        foreach ($tippdatei->zeilenJeAbschnitt() as [$abschnitt, $schluessel, $wert]) {
            if (substr($abschnitt, 0, 5) != 'Round' || substr($schluessel, 0, 2) != 'GA') {
                continue;
            }
            $runde = (int) substr($abschnitt, 5) - 1;
            $wert = $wert == '' || $wert == '-1' ? Tippdatei::KEIN_TIPP : $wert;
            if ($pokal) {
                $tipps[$runde][(int) substr($schluessel, 2, -1) - 1][(int) substr($schluessel, -1) - 1] = $wert;
            } else {
                $tipps[$runde][(int) substr($schluessel, 2) - 1] = $wert;
            }
        }

        return $tipps;
    }

    /**
     * Ein Tipp aus alleTipps(); innerhalb der Grenzen, die bis 4.5 vorbelegt waren, ohne Tipp
     * KEIN_TIPP, ausserhalb null.
     *
     * @param array<int, array<int, mixed>> $tipps
     */
    private static function tippAusAllen(array $tipps, int $runde, int $paarung, ?int $spiel, int $runden, int $paarungen, int $spiele): ?string
    {
        if ($runde < 0 || $runde >= $runden || $paarung < 0 || $paarung >= $paarungen || ($spiel !== null && ($spiel < 0 || $spiel >= $spiele))) {
            return null;
        }
        $tipp = $spiel === null ? ($tipps[$runde][$paarung] ?? Tippdatei::KEIN_TIPP) : ($tipps[$runde][$paarung][$spiel] ?? Tippdatei::KEIN_TIPP);

        return is_string($tipp) ? $tipp : Tippdatei::KEIN_TIPP;
    }

    /**
     * Die Erinnerung fuer alle Ligen zum Tippen: die Spiele bis $bisZeit, je Liga und Spieltag mit
     * Ueberschrift.
     *
     * @return array{int, string}
     */
    private function erinnerungAllerLigen(string $nachricht, int $bisZeit, int $von, int $bis): array
    {
        $alle = ($this->cfg['immeralle'] ?? '') == 1;
        $zuTippen = explode(',', $this->cfg['ligenzutippen'] ?? '');
        $dateien = [];
        foreach (is_dir($this->ligenordner) ? (scandir($this->ligenordner) ?: []) : [] as $datei) {
            if (substr($datei, -4) == '.l98' && ($alle || in_array(substr($datei, 0, -4), $zuTippen))) {
                $dateien[] = $datei;
            }
        }
        sort($dateien);

        // die anstehenden Spiele aller Ligen, die noch getippt werden koennen
        $spiele = [];
        foreach ($dateien as $datei) {
            $liga = Liga::lies($this->ligenordner . $datei);
            $einstellungen = $liga->einstellungen();
            $pokal = $liga->istPokal();
            $runden = strlen(decbin($einstellungen->mannschaften() - 1));
            foreach ($liga->spieltage as $spieltagNr => $spieltag) {
                foreach ($spieltag->partien as $partieNr => $partie) {
                    foreach ($partie->spiele as $spielNr => $werte) {
                        if (!isset($werte['AT'])) {
                            continue;
                        }
                        $heim = $partie->heim ?? 0;
                        $gast = $partie->gast ?? 0;
                        $zeit = $this->tippzeit->bis((int) $werte['AT'], $spieltag->werte['D1'] ?? '', $spieltag->werte['D2'] ?? '');
                        if ($zeit !== null && $zeit > $this->jetzt && $zeit < $bisZeit && $heim > 0 && ($werte['TI'] ?? 0) != 1) {
                            $spiele[] = [
                                'liga' => substr($datei, 0, -4),
                                'titel' => $einstellungen->name(),
                                'typ' => $einstellungen->wert('Type'),
                                'runden' => $runden,
                                'spieltag' => (string) $spieltagNr,
                                'spiel' => $pokal ? $partieNr . $spielNr : (string) $partieNr,
                                'heim' => $liga->mannschaftsname($heim),
                                'gast' => $gast > 0 ? $liga->mannschaftsname($gast) : '___',
                                'zeit' => date('d.m.Y H:i', $zeit),
                            ];
                        }
                    }
                }
            }
        }

        $anzahl = 0;
        $fehler = '';
        for ($nr = $von - 1; $nr < $bis; $nr++) {
            $felder = explode('|', $this->zeilen[$nr] ?? '');
            if (!isset($felder[10], $felder[4]) || $felder[10] == -1 || $felder[4] == '') {
                continue;
            }
            // getippt: je Liga aus der Tippdatei des Tippers (ohne Tippdatei tippt er die Liga nicht)
            $getippt = [];
            $tippt = false;
            $text = '';
            $letzteLiga = '';
            $letzterSpieltag = '';
            foreach ($spiele as $i => $s) {
                if ($i == 0 || $s['liga'] != $spiele[$i - 1]['liga']) {
                    $tippdatei = $this->tippordner . $s['liga'] . '_' . $felder[0] . '.tip';
                    $tippt = file_exists($tippdatei);
                    if ($tippt) {
                        foreach (Tippdatei::lies($tippdatei)->zeilenJeAbschnitt() as [$abschnitt, $schluessel, $wert]) {
                            if (substr($abschnitt, 0, 5) == 'Round' && substr($schluessel, 0, 2) == 'GA' && $wert != '' && $wert != '-1') {
                                foreach ($spiele as $j => $t) {
                                    if ($t['liga'] == $s['liga'] && $t['spieltag'] == substr($abschnitt, 5) && $t['spiel'] == substr($schluessel, 2)) {
                                        $getippt[$j] = true;
                                    }
                                }
                            }
                        }
                    }
                }
                if (!$tippt || isset($getippt[$i])) {
                    continue;
                }
                if ($letzteLiga != $s['liga']) {
                    $text .= "\n" . $s['titel'] . ':';
                }
                if ($letzterSpieltag != $s['spieltag'] || $letzteLiga != $s['liga']) {
                    if ($s['typ'] == 0) {
                        $text .= "\n" . $s['spieltag'] . '. ' . ($this->text[2] ?? '') . ':';
                    } else {
                        $text .= match (true) {
                            $s['spieltag'] == $s['runden'] => $this->text[374] ?? '',
                            $s['spieltag'] == $s['runden'] - 1 => $this->text[373] ?? '',
                            $s['spieltag'] == $s['runden'] - 2 => $this->text[372] ?? '',
                            $s['spieltag'] == $s['runden'] - 3 => $this->text[371] ?? '',
                            default => $s['spieltag'] . '. ' . ($this->text[370] ?? ''),
                        } . ':';
                    }
                }
                $text .= "\n" . $s['heim'] . ' - ' . $s['gast'] . ' (' . ($this->t[87] ?? '') . ' ' . $s['zeit'] . ')';
                $letzteLiga = $s['liga'];
                $letzterSpieltag = $s['spieltag'];
            }
            if ($text != '') {
                $this->schicke($felder, str_replace('[spiele]', $text, $this->ersetze($nachricht, $felder)), $anzahl, $fehler);
            }
        }

        return [$anzahl, $fehler];
    }

    /**
     * [nick], [pass] und [name] des Tippers.
     *
     * @param list<string> $felder
     */
    private function ersetze(string $nachricht, array $felder): string
    {
        $nick = $felder[0];

        return str_replace(['[nick]', '[pass]', '[name]'], [$nick, $this->kennwortAdresse . urlencode($nick), $felder[3] ?? ''], $nachricht);
    }

    /** @param list<string> $felder */
    private function schicke(array $felder, string $text, int &$anzahl, string &$fehler): void
    {
        $ergebnis = ($this->versende)($felder[4] ?? '', $text);
        if ($ergebnis === null) {
            $anzahl++;
        } else {
            $fehler .= $ergebnis;
        }
    }
}
