<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Lmo\Liga\Einstellungen;
use Lmo\Liga\Paarung;
use Lmo\Liga\Spiel;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Controller\KalenderController;
use Lmo\Web\Darstellung;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Seiten\SpieltagsMenue;
use Lmo\Web\Vorlagen;

/**
 * Die Tippabgabe fuer einen Spieltag einer Liga oder eine Runde eines Pokals (todo=edit): Tipps
 * eingeben und speichern, mit Joker, den Tipps der anderen (Tendenz, Durchschnitt), Ergebnissen
 * und Punkten. Bis 4.5 lmo-tippedit.php mit lmo-tippeditliga.php, lmo-tippeditko.php und
 * lmo-tippcalceinsicht.php.
 */
final class TippabgabeController implements Controller
{
    /** @param string $ordner Ordner des Addons (addon/tipp), dort liegen die Tipps */
    public function __construct(private string $ordner, private Tipprahmen $rahmen)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->action == 'tipp'
            && $kontext->konfiguration->schalter('eintippspiel')
            && $kontext->anfrage->text('todo') == 'edit'
            && !$kontext->anfrage->gesetzt('viewermode')
            && $kontext->sitzung->zahl('lmotipperok') == 5
            && $kontext->liga !== null;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $liga = $kontext->liga;
        if ($liga === null) {
            return new Seite('');
        }
        $cfg = $kontext->konfiguration->addon('tipp');
        $anfrage = $kontext->anfrage;
        $e = $liga->liga->einstellungen();
        $st = $anfrage->gesetzt('st') ? (int) $anfrage->text('st') : $e->aktuellerSpieltag();
        $runde = Tipprunde::fuer($liga->liga, $st);
        $tipps = $this->ordner . '/' . ($cfg['dirtipp'] ?? 'tipps/');
        $nick = $kontext->sitzung->text('lmotippername');
        $tippdatei = $tipps . substr($liga->datei, 0, -4) . '_' . $nick . '.tip';
        $skript = ($cfg['tippmodus'] ?? '') == 1 ? Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'tippabgabe_skript.html.twig', ['pfeile' => ($cfg['pfeiltipp'] ?? '') == 1, 'bilder' => $kontext->pfade->bilder]) : '';
        if (!is_readable($tippdatei)) {
            // ohne Tippdatei (die Liga nicht abonniert) brach die Seite bis 4.5 hier ab
            return new Seite($skript . $this->rahmen->html($kontext, ''));
        }
        $tippzeit = new Tippzeit((int) ($cfg['tippBis'] ?? 0), ($cfg['tippohne'] ?? '') == 1, $kontext->konfiguration->wert('deftime'), (int) ($cfg['imvorraus'] ?? -1));
        $aenderbar = static fn (int $anstoss, int $plus): bool => $tippzeit->aenderbar($anstoss, $runde->ersterTag, $runde->letzterTag, $st, $e->aktuellerSpieltag(), $plus);
        $gelesen = Tippdatei::lies($tippdatei)->runde($st, (int) $runde->plaetze, $runde->pokal ? (int) $runde->spieleJePaarung : null);
        $tippsDerRunde = $gelesen['tipps'];
        $joker = $gelesen['joker'];
        $meldungen = '';

        if ($anfrage->gesetzt('save')) {
            $abgabe = Tippabgabe::aus($anfrage, $runde, $tippsDerRunde, $joker, (int) ($cfg['tippmodus'] ?? 1), ($cfg['jokertipp'] ?? '') == 1, static fn (int $anstoss): bool => $aenderbar($anstoss, 0));
            $tippsDerRunde = $abgabe->tipps;
            if ($abgabe->joker !== null) {
                $joker = $abgabe->joker;
            }
            $gespeichert = Tippdatei::lies($tippdatei)->mitRunde($st, $tippsDerRunde, $abgabe->joker, $runde->pokal)->speichere($tippdatei);
            $t = $kontext->texteVon('tipp');
            $meldungen .= $gespeichert
                ? Tipprahmen::meldung($kontext, ($t[41] ?? '') . ($abgabe->jokerGesperrt ? ' ' . ($t[292] ?? '') : ''))
                : Tipprahmen::meldung($kontext, $kontext->text[283] ?? '', true);
            if (($cfg['akteinsicht'] ?? '') == 1 && $nick != '') {
                $einsicht = $tipps . 'einsicht/' . substr($liga->datei, 0, -4) . '_' . $st . '.ein';
                Tippdatei::lies($einsicht)->mitAbschnitt($nick, $tippsDerRunde, $abgabe->joker, $runde->pokal)->speichere($einsicht);
            }
        }

        $statistik = null;
        if (($cfg['showtendenzabs'] ?? '') == 1 || ($cfg['showtendenzpro'] ?? '') == 1 || (($cfg['showdurchschntipp'] ?? '') == 1 && ($cfg['tippmodus'] ?? '') == 1)) {
            $statistik = Einsichtstatistik::lies($tipps . 'einsicht/' . substr($liga->datei, 0, -4) . '_' . $st . '.ein', $runde->pokal);
            if ($statistik === null) {
                $meldungen .= Tipprahmen::meldung($kontext, $kontext->texteVon('tipp')[17] ?? '', true);
            }
        }

        return new Seite($skript . $this->rahmen->html($kontext, $meldungen . $this->tabelle($kontext, $e, $runde, $st, $tippsDerRunde, $joker, $statistik, $aenderbar)));
    }

    /**
     * Die Tabelle mit den Spielen des Spieltags.
     *
     * @param array<int, mixed> $tipps
     * @param \Closure(int, int): bool $aenderbar Anstoss, Sicherheitsabstand => kann noch getippt werden?
     */
    private function tabelle(Kontext $kontext, Einstellungen $e, Tipprunde $runde, int $st, array $tipps, string $joker, ?Einsichtstatistik $statistik, \Closure $aenderbar): string
    {
        $cfg = $kontext->konfiguration->addon('tipp');
        $text = $kontext->text;
        $t = $kontext->texteVon('tipp');
        $liga = $kontext->liga;
        $datei = $liga !== null ? $liga->datei : '';
        $modus = (int) ($cfg['tippmodus'] ?? 1);
        $mitJoker = ($cfg['jokertipp'] ?? '') == 1;
        $zeit = (int) $e->mitAnstosszeit();
        $ohneRemis = (int) $e->ohneUnentschieden();
        $breite = 17 + ($e->mitVerlaengerung() ? 2 : 0) + (($cfg['showtendenzabs'] ?? '') == 1 ? 2 : 0) + (($cfg['showtendenzpro'] ?? '') == 1 ? 2 : 0)
            + (($cfg['showdurchschntipp'] ?? '') == 1 ? 2 : 0) + $zeit - $ohneRemis - ($modus == 0 ? 2 : 0);
        // im Pokal der Name der Runde
        $rundenname = $st . '. ' . ($text[2] ?? '');
        if ($runde->pokal) {
            $rundenname = match ($runde->runden - $st) {
                0 => $text[374] ?? '',
                1 => $text[373] ?? '',
                2 => $text[372] ?? '',
                3 => $text[371] ?? '',
                default => $st . '. ' . ($text[370] ?? ''),
            };
        }

        // welche Spiele noch getippt werden koennen (mit einer Minute Sicherheitsabstand) und ob der Joker noch frei ist
        $offen = [];
        $jokerFrei = true;
        foreach ($runde->paarungen as $i => $paarung) {
            foreach ($runde->pokal ? array_keys($paarung->spiele) : [0] as $n) {
                $offen[$i][$n] = $aenderbar((int) ($paarung->spiele[$n]->anstoss ?? 0), 1);
                if ($mitJoker && $joker == ($runde->pokal ? ($i + 1) . ($n + 1) : (string) ($i + 1)) && !$offen[$i][$n]) {
                    $jokerFrei = false;
                }
            }
        }

        $punkteRegeln = Punkte::ausKonfiguration($cfg);
        $punkteSpieltag = 0;
        $speichern = false;
        $zeilen = [];
        foreach ($runde->paarungen as $i => $paarung) {
            if (!($paarung->heim > 0 && $paarung->gast > 0)) {
                continue;
            }
            foreach ($runde->pokal ? $paarung->spiele : [$paarung->spiele[0]] as $n => $spiel) {
                $tipp = $tipps[$i] ?? null;
                if ($runde->pokal) {
                    $tipp = is_array($tipp) ? ($tipp[$n] ?? null) : null;
                }
                $zeile = $this->zeile($kontext, $e, $runde, $paarung, $spiel, $i, $n, is_array($tipp) ? $tipp : ['_', '_'], $joker, $statistik, $offen[$i][$n] ?? false, $jokerFrei, $punkteRegeln, $datei);
                $speichern = $speichern || $zeile['offen'] === true;
                $punkte = $zeile['punkte'];
                if ((is_int($punkte) || is_float($punkte)) && $punkte > 0) {
                    $punkteSpieltag += $punkte;
                }
                $zeile['ueberschrift'] = $runde->pokal && $e->mitSpielUmPlatz3() && $st == $runde->runden ? ($i == 1 ? '&nbsp;<br>' : '') . ($text[419 + $i] ?? '') : null;
                $zeile['ersteDerPaarung'] = $n == 0;
                $zeile['leerzeileDanach'] = $runde->pokal && count($paarung->spiele) > 1 && $i < $runde->plaetze - 1 && $n == array_key_last($paarung->spiele);
                $zeilen[] = $zeile;
            }
        }
        $faktor = $e->torfaktor();
        $gesperrt = (int) ($cfg['imvorraus'] ?? -1) >= 0 && $st > $e->aktuellerSpieltag() + (int) ($cfg['imvorraus'] ?? -1);
        $verein = $kontext->sitzung->text('lmotipperverein');
        $addr = $kontext->adresse . '?action=tipp&amp;todo=edit&amp;file=' . $datei . '&amp;st=';

        return Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'tippabgabe.html.twig', [
            'text' => $text,
            't' => $t,
            'cfg' => $cfg + array_fill_keys(['showtendenzabs', 'showtendenzpro', 'showdurchschntipp', 'tippmodus', 'pfeiltipp', 'jokertipp', 'tippBis'], ''),
            'adresse' => $kontext->adresse,
            'tipper' => $kontext->sitzung->text('lmotippername') . ($verein != '' ? ' - ' . $verein : ''),
            'spieltagsmenue' => (new SpieltagsMenue($text, $kontext->pfade->vorlagen))->zeige($liga?->liga, $st, $addr),
            'datei' => $datei,
            'st' => $st,
            'pokal' => $runde->pokal,
            'zeit' => $zeit,
            'ohneRemis' => $ohneRemis,
            'mitVerlaengerung' => $e->mitVerlaengerung(),
            'breite' => $breite,
            'rundenname' => $rundenname,
            'ersterTag' => $e->mitSpieltagsdatum() ? $runde->ersterTag : '',
            'letzterTag' => $e->mitSpieltagsdatum() ? $runde->letzterTag : '',
            'faktortext' => $faktor > 0 && $faktor != 1 ? '(' . ($text[553 + (int) log10($faktor)] ?? '') . ')' : '',
            'zeilen' => $zeilen,
            'gesperrt' => $gesperrt,
            'speichern' => $speichern,
            'punkteSpieltag' => $punkteSpieltag,
            'zurueck' => $st > 1 ? $addr . ($st - 1) : null,
            'weiter' => $st < $runde->runden ? $addr . ($st + 1) : null,
            'bilder' => $kontext->pfade->bilder,
        ]);
    }

    /**
     * Eine Zeile: ein Spiel mit Tipp, Statistik, Ergebnis, Punkten und Notizen.
     *
     * @param array<mixed> $tipp [Heim, Gast]
     * @return array<string, mixed>
     */
    private function zeile(Kontext $kontext, Einstellungen $e, Tipprunde $runde, Paarung $paarung, Spiel $spiel, int $i, int $n, array $tipp, string $joker, ?Einsichtstatistik $statistik, bool $offen, bool $jokerFrei, Punkte $punkteRegeln, string $datei): array
    {
        $cfg = $kontext->konfiguration->addon('tipp');
        $text = $kontext->text;
        $t = $kontext->texteVon('tipp');
        $pokal = $runde->pokal;
        $faktor = $e->torfaktor();
        $heim = $paarung->heimMannschaft()->anzeigename();
        $gast = $paarung->gastMannschaft()->anzeigename();
        // kein Tipp wird leer angezeigt
        $leer = static fn (mixed $wert): string => $wert === Tippdatei::KEIN_TIPP || $wert == '-1' || !is_scalar($wert) ? '' : (string) $wert;
        $a = $leer($tipp[0] ?? '');
        $b = $leer($tipp[1] ?? '');
        $feld = $pokal ? $i . $n : (string) $i;
        $jokerSpiel = $pokal ? ($i + 1) . ($n + 1) : (string) ($i + 1);
        $mitJoker = ($cfg['jokertipp'] ?? '') == 1;

        // die Tipps der anderen; erst nach dem Ergebnis, wenn einsichterst=2
        $verborgen = ($cfg['einsichterst'] ?? '') == 2 && !($spiel->toreHeim !== null && $spiel->toreGast !== null);
        $zahlen = $statistik?->spiel($i, $pokal ? $n : null) ?? ['t1' => 0, 't0' => 0, 't2' => 0, 'a' => 0, 'b' => 0, 'anz' => 0];
        $prozent = static fn (int $wert): string => number_format($wert / $zahlen['anz'] * 100, 0, '.', ',') . '%';
        $nachkomma = $zahlen['a'] < 10 && $zahlen['b'] < 10 ? 1 : 0;
        $schnitt = static fn (int|float $tore): string => number_format((float) ($pokal ? $tore : Darstellung::mitFaktor($tore, $faktor)) / $zahlen['anz'], $nachkomma, '.', ',');

        // die Punkte fuer den Tipp
        $punkte = -1;
        if ($a != '' && $b != '' && $spiel->toreHeim !== null && $spiel->toreGast !== null) {
            $jokerfaktor = $mitJoker && $joker == $jokerSpiel ? (is_numeric($cfg['jokertippmulti'] ?? null) ? +$cfg['jokertippmulti'] : 1) : 1;
            [$punkte] = $punkteRegeln->fuer((int) $a, (int) $b, (int) $spiel->toreHeimText(), (int) $spiel->toreGastText(), $pokal ? 0 : $spiel->wertung, $spiel->zusatzText($text[0] ?? '', $text[1] ?? ''), $text[0] ?? '', $text[1] ?? '', $jokerfaktor, (bool) $spiel->ohneTippwertung);
        }
        if ($punkte == -1) {
            $punkteHtml = '-';
        } elseif ($punkte == -2) {
            $punkteHtml = $t[230] ?? '';
        } elseif (($cfg['tippmodus'] ?? '') == 1) {
            $punkteHtml = (string) $punkte;
        } elseif ($punkte > 0) {
            $punkteHtml = '<img src="' . $kontext->pfade->bilder . '/right.svg" height="20" border="0" alt="&#9786;">' . ($punkte > 1 ? '+' . ($punkte - 1) : '');
        } else {
            // in der Liga wie bis 4.5 mit dem Tippfehler im Dateinamen
            $punkteHtml = '<img src="' . $kontext->pfade->bilder . '/wrong.' . ($pokal ? 'svg' : 'scg') . '" height="20" border="0" alt="&#9785;">';
        }
        $tendenz = -1;
        if ($a != '' && $b != '') {
            $tendenz = $a > $b ? 1 : ($a == $b ? 0 : 2);
        }
        $sieger = $pokal ? $paarung->sieger() : 0;

        return [
            'feld' => $feld,
            'anstoss' => $e->mitAnstosszeit() ? ($spiel->anstoss !== null ? KalenderController::uebersetzt(date($e->datumsformat($kontext->konfiguration->datumsformat()), $spiel->anstoss), $kontext->texteVon('date')) : '') : null,
            'heim' => $heim,
            'gast' => $gast,
            'heimFavorit' => $e->lieblingsmannschaft() > 0 && $e->lieblingsmannschaft() == $paarung->heim,
            'gastFavorit' => $e->lieblingsmannschaft() > 0 && $e->lieblingsmannschaft() == $paarung->gast,
            'heimKlasse' => $sieger == 1 ? 'lmoTurnierSieger nobr' : ($sieger == 2 ? 'lmoTurnierVerlierer nobr' : 'nobr'),
            'gastKlasse' => $sieger == 1 ? 'lmoTurnierVerlierer nobr' : ($sieger == 2 ? 'lmoTurnierSieger nobr' : 'nobr'),
            'verborgen' => $verborgen,
            'tendenzAbsolut' => $zahlen['t1'] . '-' . $zahlen['t0'] . '-' . $zahlen['t2'],
            'tendenzProzent' => $zahlen['anz'] > 0 ? $prozent($zahlen['t1']) . '-' . $prozent($zahlen['t0']) . '-' . $prozent($zahlen['t2']) : '&nbsp;',
            'durchschnitt' => $zahlen['anz'] > 0 ? $schnitt($zahlen['a']) . ':' . $schnitt($zahlen['b']) : '&nbsp;',
            'offen' => $offen,
            'a' => $a,
            'b' => $b,
            'tendenz' => $tendenz,
            'joker' => $jokerSpiel,
            'jokerGesetzt' => $joker == $jokerSpiel,
            'jokerGesperrt' => !$offen || !$jokerFrei,
            'toreHeim' => $pokal ? $spiel->toreHeimText() : $spiel->toreHeimText($faktor),
            'toreGast' => $pokal ? $spiel->toreGastText() : $spiel->toreGastText($faktor),
            'zusatz' => $spiel->zusatzText($text[0] ?? '', $text[1] ?? ''),
            'punkte' => $punkte,
            'punkteHtml' => $punkteHtml,
            'notizen' => $this->notizen($kontext, $e, $spiel, $heim, $gast, $pokal, $datei),
        ];
    }

    /** Spielbericht und Notiz als Symbole mit Hinweisfenster, wie bis 4.5 in Liga und Pokal leicht verschieden. */
    private function notizen(Kontext $kontext, Einstellungen $e, Spiel $spiel, string $heim, string $gast, bool $pokal, string $datei): string
    {
        $text = $kontext->text;
        $t = $kontext->texteVon('tipp');
        $bilder = $kontext->pfade->bilder;
        $faktor = $e->torfaktor();
        $html = '';
        /** Mannschaftsicons finden **/
        $wappenHeim = '';
        $wappenGast = '';
        if ($e->mitSpielberichten() || $spiel->notiz != '' || (!$pokal && $spiel->wertung > 0)) {
            $wappenHeim = $kontext->darstellung->wappen($datei, $heim, ' alt=""') . ($pokal ? '' : ' ');
            $wappenGast = $kontext->darstellung->wappen($datei, $gast, ' alt=""') . ($pokal ? '' : ' ');
        }
        /** Spielbericht verlinken **/
        if ($e->mitSpielberichten()) {
            if ($spiel->bericht != '') {
                $bericht = '<strong>' . $heim . $wappenHeim . ' - ' . $wappenGast . $gast . '</strong>' . nl2br($text[270] ?? '');
                $html .= '<span onmouseover="showLMOTooltip(this)" onmouseout="hideLMOTooltip()" data-info="' . htmlspecialchars($bericht) . '" class="tooltip-trigger"><a href="' . $spiel->bericht . '" target="_blank"><img src="' . $bilder . '/lmo-st1.svg" height="15" class="svg-no-style" border="0" alt=""></a></span>&nbsp;';
            } else {
                $html .= '&nbsp;';
            }
        }
        /** Notizen anzeigen **/
        $tore = $spiel->toreHeimText($faktor) . ':' . $spiel->toreGastText($faktor);
        if ($pokal ? ($spiel->notiz != '' || (int) $spiel->ohneTippwertung > 0) : ($spiel->notiz != '' || $spiel->wertung > 0 || (int) $spiel->ohneTippwertung > 0)) {
            if ($pokal) {
                $notiz = '<strong>' . $heim . $wappenHeim . ' - ' . $wappenGast . $gast . '&nbsp;&nbsp;' . $tore . '</strong>';
                if ($spiel->notiz != '') {
                    $notiz .= '<br><strong>' . ($text[22] ?? '') . ':</strong> ' . $spiel->notiz . '<br>';
                }
                if ((int) $spiel->ohneTippwertung == 1) {
                    $notiz .= '<br><br><strong>' . ($text[487] ?? '') . '</strong>' . ($t[231] ?? '');
                }

                return $html . '<span onmouseover="showLMOTooltip(this)" onmouseout="hideLMOTooltip()" data-info="' . htmlspecialchars(nl2br($notiz)) . '" class="tooltip-trigger"><img src="' . $bilder . '/lmo-st2.svg" height="15" class="svg-no-style" border="0" alt=""></span>';
            }
            //Grüner Tisch: Beidseitiges Ergebnis vorbereiten
            $beide = $spiel->wertung == 3 ? ' / ' . $spiel->toreGastText($faktor) . ':' . $spiel->toreHeimText($faktor) : '';
            $notiz = '<strong>' . $heim . $wappenHeim . ' - ' . $wappenGast . $gast . '&nbsp;&nbsp;' . $tore . $beide . '</strong>';
            if ($e->mitVerlaengerung()) {
                $notiz .= '&nbsp;' . $spiel->zusatzText($text[0] ?? '', $text[1] ?? '') . '<br>';
            }
            if ($spiel->wertung == 1) {
                $notiz .= '<strong>' . ($text[219] ?? '') . ':</strong>' . $heim . '&nbsp;' . ($text[211] ?? '') . '<br>';
            }
            if ($spiel->wertung == 2) {
                $notiz .= '<strong>' . ($text[219] ?? '') . ':</strong>' . $gast . '&nbsp;' . ($text[211] ?? '') . '<br>';
            }
            if ($spiel->wertung == 3) {
                $notiz .= '<strong>' . ($text[219] ?? '') . ':</strong>' . ($text[212] ?? '') . '<br>';
            }
            if ($spiel->notiz != '') {
                $notiz .= '<br><strong>' . ($text[22] ?? '') . ':</strong>' . $spiel->notiz . '<br>';
            }
            if ((int) $spiel->ohneTippwertung == 1) {
                $notiz .= '<br><strong>' . ($text[487] ?? '') . '</strong>' . ($t[231] ?? '');
            }

            return $html . '<span onmouseover="showLMOTooltip(this)" onmouseout="hideLMOTooltip()" data-info="' . htmlspecialchars(nl2br($notiz)) . '" class="tooltip-trigger"><img src="' . $bilder . '/lmo-st2.svg" height="15" class="svg-no-style" alt=""></span>';
        }

        return $html . '&nbsp;';
    }
}
