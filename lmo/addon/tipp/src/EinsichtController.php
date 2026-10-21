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

use Lmo\Liga\Paarung;
use Lmo\Liga\Spiel;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Seiten\SpieltagsMenue;
use Lmo\Web\Vorlagen;

/**
 * Die Einsicht eines Spieltags (todo=einsicht): die Tipps aller Tipper als Kreuztabelle, nach Nick
 * sortiert und in Seiten, mit Punkten, Tendenz und Durchschnittstipp. Auch fuer nicht angemeldete
 * Besucher. Bis 4.5 lmo-tippeinsicht.php mit lmo-tippcalceinsicht.php.
 */
final class EinsichtController implements Controller
{
    /** @param string $ordner Ordner des Addons (addon/tipp), dort liegen die Tipps */
    public function __construct(private string $ordner, private Tipprahmen $rahmen)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        return $kontext->action == 'tipp'
            && TippAddon::an($kontext->konfiguration)
            && $kontext->anfrage->text('todo') == 'einsicht'
            && !$kontext->anfrage->gesetzt('viewermode')
            && $kontext->liga !== null;
    }

    public function zeige(Kontext $kontext): Seite
    {
        return new Seite($this->rahmen->auchFuerGaeste(
            $kontext,
            fn (bool $angemeldet): string => ($kontext->konfiguration->addon('tipp')['tippeinsicht'] ?? '') == 1 ? $this->einsicht($kontext, $angemeldet) : ''
        ));
    }

    private function einsicht(Kontext $kontext, bool $angemeldet): string
    {
        $liga = $kontext->liga;
        if ($liga === null) {
            return '';
        }
        $cfg = $kontext->konfiguration->addon('tipp');
        $text = $kontext->text;
        $t = $kontext->texteVon('tipp');
        $anfrage = $kontext->anfrage;
        $e = $liga->liga->einstellungen();
        $st = $anfrage->text('st') == '' || $anfrage->text('st') == 0 ? $e->aktuellerSpieltag() : (int) $anfrage->text('st');
        $runde = Tipprunde::fuer($liga->liga, $st);
        $datei = $liga->datei;
        $statistik = Einsichtstatistik::lies($this->ordner . '/' . ($cfg['dirtipp'] ?? 'tipps/') . 'einsicht/' . substr($datei, 0, -4) . '_' . $st . '.ein', $runde->pokal);
        $meldung = $statistik === null ? Tipprahmen::meldung($kontext, $t[17] ?? '', true) : '';
        $tipper = $statistik?->tipper() ?? [];
        $anzahl = count($tipper);
        // die Tipper nach Nick (wie bis 4.5 ueber den Nick klein geschrieben und die Nummer als Text)
        $reihenfolge = [];
        foreach ($tipper as $nr => $einer) {
            $reihenfolge[] = strtolower($einer['nick']) . (50000000 + $nr);
        }
        sort($reihenfolge, SORT_STRING);
        $sortiert = array_map(static fn (string $schluessel): int => intval(substr($schluessel, -6)), $reihenfolge);

        $jeSeite = (int) ($cfg['anzseite'] ?? 20);
        if ($jeSeite < 1) {
            $jeSeite = 20;
        }
        $start = $anfrage->zahl('start');
        if ($start >= $anzahl) {
            $start = 0;
        }
        $ende = min($start + $jeSeite, $anzahl);
        $seiten = $anzahl / $jeSeite;
        $auswahl = [];
        $kuerzel = static fn (int $platz): string => strtolower(substr($tipper[$sortiert[$platz] ?? 0]['nick'] ?? '', 0, 3));
        for ($i = 0; $i < $seiten; $i++) {
            $von = $i * $jeSeite;
            $bis = min(($i + 1) * $jeSeite - 1, $anzahl - 1);
            $auswahl[] = ['von' => $von, 'name' => $kuerzel($von) . '-' . $kuerzel($bis), 'umbruch' => ($i + 1) % 10 == 0];
        }

        // welche Spiele noch verborgen sind: bis zum Tippschluss (einsichterst=1) oder bis zum Ergebnis (2)
        $tippzeit = new Tippzeit((int) ($cfg['tippBis'] ?? 0), ($cfg['tippohne'] ?? '') == 1, $kontext->konfiguration->wert('deftime'), (int) ($cfg['imvorraus'] ?? -1));
        $spiele = [];
        foreach ($runde->paarungen as $i => $paarung) {
            if (!($paarung->heim > 0 && $paarung->gast > 0)) {
                continue;
            }
            for ($n = 0; $n < ($runde->pokal ? $runde->spieleJePaarung : 1); $n++) {
                $spiel = $paarung->spiele[$n] ?? null;
                if ($spiel === null) {
                    continue;
                }
                $verborgen = match ((int) ($cfg['einsichterst'] ?? 0)) {
                    1 => $tippzeit->aenderbar((int) $spiel->anstoss, $runde->ersterTag, $runde->letzterTag, $st, $e->aktuellerSpieltag(), 0),
                    2 => !($spiel->toreHeim !== null && $spiel->toreGast !== null),
                    default => false,
                };
                $spiele[] = ['i' => $i, 'n' => $n, 'paarung' => $paarung, 'spiel' => $spiel, 'verborgen' => $verborgen];
            }
        }

        $zeilen = [];
        $kopf = $this->kopf($kontext, $spiele);
        $zeilen[] = ['th' => '', 'zellen' => $kopf, 'ende' => '<th valign="bottom">' . ($text[37] ?? '') . '</th>' . "\n" . '          <th>&nbsp;</th>'];
        $punkteRegeln = Punkte::ausKonfiguration($cfg);
        $tabelle = $kontext->adresse . '?action=tipp&amp;todo=tabelle&amp;file=' . $datei . '&amp;endtab=&amp;nick=';
        for ($platz = $start; $platz < $ende; $platz++) {
            $einer = $tipper[$sortiert[$platz]];
            $summe = 0;
            $zellen = [];
            foreach ($spiele as $s) {
                [$html, $punkte] = $this->zelle($kontext, $s, $einer, $punkteRegeln, $runde->pokal);
                $zellen[] = $html;
                if ($punkte > 0) {
                    $summe += $punkte;
                }
            }
            $nick = htmlspecialchars($einer['nick']);
            // den Namen kennt die Einsicht nicht (bis 4.5 blieb er leer), nur den Nick
            $name = ($cfg['shownick'] ?? '') == 1 ? (($cfg['showname'] ?? '') == 1 ? ' (' . $nick . ')' : $nick) : '';
            $link = ($cfg['tipptabelle1'] ?? '') == 1 && !$runde->pokal
                ? "\n" . '            <a href="' . $tabelle . rawurlencode($einer['nick']) . '" title="' . ($t[181] ?? '') . ' ' . $nick . '"><img src="' . $kontext->pfade->bilder . '/tabelle.gif" border="0" width="10" height="12" alt="' . ($t[172] ?? '') . '"></a>'
                : '';
            $zeilen[] = ['th' => $name, 'zellen' => $zellen, 'ende' => '<td class="lmoFrontMarkierung" align="right">' . $summe . '</td>' . "\n" . '          <td class="nobr" align="left">&nbsp; ' . $link . "\n" . '          </td>'];
        }
        // Tendenz und Durchschnittstipp aller Tipper
        $tendenz = [];
        $schnitt = [];
        foreach ($spiele as $s) {
            $z = $statistik?->spiel($s['i'], $runde->pokal ? $s['n'] : null) ?? ['t1' => 0, 't0' => 0, 't2' => 0, 'a' => 0, 'b' => 0, 'anz' => 0];
            $tendenz[] = '<td align="center" class="lmoLeer nobr">' . ($anzahl > 0 && !$s['verborgen'] ? $z['t1'] . '-' . $z['t0'] . '-' . $z['t2'] : '') . "\n" . '          </td>';
            $wert = '';
            if ($anzahl > 0 && ($cfg['tippmodus'] ?? '') == 1 && !$s['verborgen'] && $z['anz'] > 0) {
                $nachkomma = $z['a'] < 10 && $z['b'] < 10 ? 1 : 0;
                $wert = number_format($z['a'] / $z['anz'], $nachkomma, '.', ',') . ':' . number_format($z['b'] / $z['anz'], $nachkomma, '.', ',');
            }
            $schnitt[] = '<td align="center" class="lmoLeer nobr"><strong>' . $wert . "\n" . '          </strong></td>';
        }
        $leer = '<td class="lmoLeer" colspan="2">&nbsp;</td>';
        $zeilen[] = ['th' => $anzahl > 0 ? ($t[188] ?? '') : '', 'zellen' => $tendenz, 'ende' => $leer];
        $zeilen[] = ['th' => $anzahl > 0 && ($cfg['tippmodus'] ?? '') == 1 ? 'Ø-' . ($t[30] ?? '') : '', 'zellen' => $schnitt, 'ende' => $leer];
        $zeilen[] = ['th' => '', 'zellen' => $kopf, 'ende' => '<th valign="top">' . ($text[37] ?? '') . '</th>' . "\n" . '          <th>&nbsp;</th>'];

        $verein = $kontext->sitzung->text('lmotipperverein');

        return $meldung . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'einsicht.html.twig', [
            't' => $t,
            'titel' => $angemeldet ? htmlspecialchars($kontext->sitzung->text('lmotippername') . ($verein != '' ? ' - ' . $verein : '')) : ($t[158] ?? ''),
            'einsichterst' => (int) ($cfg['einsichterst'] ?? 0),
            'hinweis' => ($t[220] ?? '') . ' ' . ($t[215 + (int) ($cfg['einsichterst'] ?? 0)] ?? ''),
            'spieltagsmenue' => (new SpieltagsMenue($text, $kontext->pfade->vorlagen))->zeige($liga->liga, $st, $kontext->adresse . '?action=tipp&amp;todo=einsicht&amp;file=' . $datei . '&amp;start=' . $start . '&amp;st='),
            'seiten' => $seiten,
            'auswahl' => $auswahl,
            'zeilenAuswahl' => (int) floor($seiten / 10) + 1,
            'start' => $start,
            'seitenlink' => $kontext->adresse . '?action=tipp&amp;todo=einsicht&amp;file=' . $datei . '&amp;st=' . $st . '&amp;start=',
            'zeilen' => $zeilen,
        ]);
    }

    /**
     * Die Kopfzeile (oben und unten): je Spiel die Mannschaften und das Ergebnis.
     *
     * @param list<array{i: int, n: int, paarung: Paarung, spiel: Spiel, verborgen: bool}> $spiele
     * @return list<string>
     */
    private function kopf(Kontext $kontext, array $spiele): array
    {
        $faktor = $kontext->liga?->liga->einstellungen()->torfaktor() ?? 1;
        $zellen = [];
        foreach ($spiele as $s) {
            $spiel = $s['spiel'];
            $ohneWertung = (int) $spiel->ohneTippwertung == 1;
            $zellen[] = '<th align="center" valign="center" class="nobr">'
                . ($s['n'] == 0 ? $s['paarung']->heimMannschaft()->kurzname() . '<br>' : '')
                . ($ohneWertung ? "<acronym title='" . ($kontext->texteVon('tipp')[231] ?? '') . "'><del>" : '')
                . $spiel->toreHeimText($faktor) . ':' . $spiel->toreGastText($faktor)
                . ($ohneWertung ? '</del></acronym>' : '')
                . ($s['n'] == 0 ? '<br>' . $s['paarung']->gastMannschaft()->kurzname() : '')
                . "\n" . '          </th>';
        }

        return $zellen;
    }

    /**
     * Der Tipp eines Tippers fuer ein Spiel mit seinen Punkten; leer, solange das Spiel verborgen ist.
     *
     * @param array{i: int, n: int, paarung: Paarung, spiel: Spiel, verborgen: bool} $s
     * @param array{nick: string, joker: string, tipps: array<int|string, array{0: string, 1: string}>} $tipper
     * @return array{0: string, 1: int|float} HTML der Zelle, Punkte (-1 ohne)
     */
    private function zelle(Kontext $kontext, array $s, array $tipper, Punkte $regeln, bool $pokal): array
    {
        if ($s['verborgen']) {
            return ['<td align="center" class="nobr">' . "\n" . '          </td>', -1];
        }
        $cfg = $kontext->konfiguration->addon('tipp');
        $text = $kontext->text;
        $spiel = $s['spiel'];
        $tipp = $tipper['tipps'][$pokal ? $s['i'] . '-' . $s['n'] : $s['i']] ?? [Tippdatei::KEIN_TIPP, Tippdatei::KEIN_TIPP];
        $a = $tipp[0] == -1 ? Tippdatei::KEIN_TIPP : $tipp[0];
        $b = $tipp[1] == -1 ? Tippdatei::KEIN_TIPP : $tipp[1];
        $zahl = static fn (string $name): int|float => is_numeric($cfg[$name] ?? null) ? +$cfg[$name] : 0;
        $joker = 1;
        $punkte = -1;
        if ($a != Tippdatei::KEIN_TIPP) {
            if (($cfg['jokertipp'] ?? '') == 1 && $tipper['joker'] == ($pokal ? ($s['i'] + 1) . ($s['n'] + 1) : (string) ($s['i'] + 1))) {
                $joker = $zahl('jokertippmulti');
            }
            if ($spiel->toreHeim !== null) {
                [$punkte] = $regeln->fuer((int) $a, (int) $b, (int) $spiel->toreHeimText(), (int) $spiel->toreGastText(), $pokal ? 0 : $spiel->wertung, $spiel->zusatzText($text[0] ?? '', $text[1] ?? ''), $text[0] ?? '', $text[1] ?? '', $joker, (bool) $spiel->ohneTippwertung);
            }
        }
        if (($cfg['tippmodus'] ?? '') == 1) {
            // Ergebnis-Tippmodus: Ergebnis fett, richtige Tordifferenz mit fettem Doppelpunkt, richtige Tore fett
            $html = '<td align="center" class="' . ($punkte > $zahl('rtor') * $joker ? 'lmoBackMarkierung nobr' : 'nobr') . '">';
            $d = ['', '', '', ''];
            if ($punkte == $zahl('rergebnis') * $joker || $punkte == ($zahl('rergebnis') + $zahl('rremis')) * $joker) {
                $d = ['<strong>', '', '', '</strong>'];
            } elseif ($punkte == $zahl('rtendenzdiff') * $joker || $punkte == ($zahl('rtendenzdiff') + $zahl('rremis')) * $joker) {
                $d = ['', '<strong>', '</strong>', ''];
            }
            if ($zahl('rtor') > 0 && ($punkte == $zahl('rtor') * $joker || $punkte == ($zahl('rtendenz') + $zahl('rtor')) * $joker)) {
                if ($spiel->toreHeimText() == $a) {
                    $d[0] = '<strong>';
                    $d[1] = '</strong>';
                } elseif ($spiel->toreGastText() == $b) {
                    $d[2] = '<strong>';
                    $d[3] = '</strong>';
                }
            }
            $html .= ($joker > 1 ? "<em class='lmoFrontMarkierung'>" : '') . $d[0] . $a . $d[1] . ':' . $d[2] . $b . $d[3] . ($joker > 1 ? '</em>' : '')
                . ($punkte >= 0 ? ' <small>' . $punkte . '</small>' : '&nbsp;');
        } else {
            // Tendenz-Modus (wie bis 4.5 mit nicht geschlossenem Absatz beim Joker)
            $html = '<td align="center" class="' . ($punkte > 0 ? 'lmoBackMarkierung nobr' : 'nobr') . '">' . ($joker > 1 ? "<p class='lmoFrontMarkierung'>" : '');
            if ($a == Tippdatei::KEIN_TIPP || $b == Tippdatei::KEIN_TIPP) {
                $html .= '_';
            } elseif ($a == $b) {
                $html .= '0';
            } elseif ($a > $b) {
                $html .= '1';
            } else {
                $html .= '2';
            }
        }

        return [$html . "\n" . '          </td>', $punkte];
    }
}
