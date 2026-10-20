<?php

declare(strict_types=1);

namespace Lmo\Addon\Tipp;

use Lmo\Liga\Rohdaten\Liga;
use Lmo\Web\Controller\Controller;
use Lmo\Web\Controller\KalenderController;
use Lmo\Web\Darstellung;
use Lmo\Web\Kontext;
use Lmo\Web\Seite;
use Lmo\Web\Vorlagen;

/**
 * Die Tippuebersicht eines angemeldeten Tippers (viewermode): die Spiele der naechsten Tage aus
 * allen seinen Ligen zum Tippen, mit Tendenzen der anderen Tipper, Joker, Ergebnissen und Punkten;
 * blaettern um viewertage Tage (start). Gespeichert wird in die Tippdateien und die Einsicht.
 * Bis 4.5 lmo-tippviewer.php mit lmo-tippopenfileviewer.php, lmo-tippsavefileviewer.php,
 * lmo-tippsaveeinsichtviewer.php und lmo-tippcalceinsichtviewer.php.
 */
final class UebersichtController implements Controller
{
    /** @param string $ordner Ordner des Addons (addon/tipp), dort liegen die Tipps */
    public function __construct(private string $ordner, private Tipprahmen $rahmen)
    {
    }

    public function zustaendig(Kontext $kontext): bool
    {
        // wie bis 4.5 zeigt viewermode die Uebersicht, gleich welches todo
        return $kontext->action == 'tipp'
            && $kontext->konfiguration->schalter('eintippspiel')
            && $kontext->anfrage->gesetzt('viewermode')
            && $kontext->sitzung->text('lmotipperok') == 5;
    }

    public function zeige(Kontext $kontext): Seite
    {
        $inhalt = ($kontext->konfiguration->addon('tipp')['viewertipp'] ?? null) == 1 ? $this->uebersicht($kontext) : '';

        return new Seite($this->rahmen->html($kontext, $inhalt));
    }

    private function uebersicht(Kontext $kontext): string
    {
        $cfg = $kontext->konfiguration->addon('tipp');
        $text = $kontext->text;
        $t = $kontext->texteVon('tipp');
        $anfrage = $kontext->anfrage;
        $nick = $kontext->sitzung->text('lmotippername');
        $tipps = $this->ordner . '/' . ($cfg['dirtipp'] ?? 'tipps/');
        $tage = (int) ($cfg['viewertage'] ?? 0);
        $ergebnismodus = ($cfg['tippmodus'] ?? null) == 1;
        $jokertipp = ($cfg['jokertipp'] ?? null) == 1;
        $tippzeit = new Tippzeit((int) ($cfg['tippBis'] ?? 0), ($cfg['tippohne'] ?? '') == 1, $kontext->konfiguration->wert('deftime'), (int) ($cfg['imvorraus'] ?? -1));

        // der Zeitraum: ab heute + start Tage, viewertage Tage lang; beim Speichern der gezeigte
        $speichern = $anfrage->text('save') == 1;
        if ($speichern) {
            $start = (int) trim($anfrage->text('xstart'));
            $von = (int) trim($anfrage->text('xnow'));
            $bis = (int) trim($anfrage->text('xthen'));
        } else {
            $start = $anfrage->zahl('start');
            $von = (int) strtotime('+' . $start . ' day');
            $bis = (int) strtotime('+' . ($start + $tage) . ' day');
        }
        $vonText = date('d.m.Y', $von);
        $von = (int) mktime(0, 0, 0, (int) substr($vonText, 3, 2), (int) substr($vonText, 0, 2), (int) substr($vonText, -4));
        $bisTag = date('d.m.Y', $bis);
        $bis = (int) mktime(0, 0, 0, (int) substr($bisTag, 3, 2), (int) substr($bisTag, 0, 2), (int) substr($bisTag, -4));
        $bisText = date('d.m.Y', $bis - 1);

        $spiele = [];
        $ligen = $kontext->pfade->lmo . '/' . $kontext->konfiguration->ligenordner();
        foreach ($this->ligen($ligen, $tipps, $nick, $cfg) as $datei) {
            if (substr($datei, -4) == '.l98') {
                array_push($spiele, ...Tippuebersicht::sammle(Liga::lies($ligen . basename($datei)), substr($datei, 0, -4), $tipps . substr($datei, 0, -4) . '_' . $nick . '.tip', $von, $bis, ($cfg['tippohne'] ?? '') == 1, $kontext->konfiguration->wert('deftime'), $jokertipp, $tippzeit, $text));
            }
        }

        $meldungen = '';
        if ($speichern) {
            $meldungen = $this->speichere($kontext, $spiele, $tipps, $nick, $tippzeit);
            if ($meldungen === null) {
                // eine Datei liess sich nicht schreiben: wie bis 4.5 nur die Meldung
                return Tipprahmen::meldung($kontext, $text[283] ?? '', true);
            }
        }
        $statistik = $this->statistik($cfg, $spiele, $tipps);

        $breite = 17 + ($ergebnismodus && ($cfg['pfeiltipp'] ?? null) == 1 ? 2 : 0) + (($cfg['showtendenzabs'] ?? null) == 1 ? 2 : 0)
            + (($cfg['showtendenzpro'] ?? null) == 1 ? 2 : 0) + (($cfg['showdurchschntipp'] ?? null) == 1 ? 2 : 0) + ($jokertipp ? 1 : 0);
        $punkte = Punkte::ausKonfiguration($cfg);
        $zeilen = [];
        $speicherbar = false;
        foreach ($spiele as $i => $s) {
            $zeile = $this->zeile($kontext, $s, $i, $statistik[$i] ?? null, $punkte, $tippzeit);
            $speicherbar = $speicherbar || $zeile['aenderbar'];
            $zeile['liga'] = $i == 0 || $s->liga != $spiele[$i - 1]->liga;
            $zeile['kopf'] = $zeile['liga'] || $s->spieltag != $spiele[$i - 1]->spieltag ? $this->kopf($kontext, $s) : null;
            $zeilen[] = $zeile;
        }
        $verein = $kontext->sitzung->text('lmotipperverein');
        $blaettern = $kontext->adresse . '?action=tipp&amp;todo=edit&amp;file=&amp;viewermode=1&amp;start=';

        return $meldungen . Vorlagen::zeige(dirname(__DIR__) . '/vorlagen', 'uebersicht.html.twig', [
            'text' => $text,
            't' => $t,
            'bilder' => $kontext->pfade->bilder,
            'adresse' => $kontext->adresse,
            'titel' => $nick . ($verein != '' ? ' - ' . $verein : ''),
            'tippBis' => $cfg['tippBis'] ?? '',
            'vonText' => $vonText,
            'bisText' => $bisText,
            'start' => $start,
            'von' => $von,
            'bis' => $bis,
            'breite' => $breite,
            'ergebnismodus' => $ergebnismodus,
            'pfeile' => ($cfg['pfeiltipp'] ?? null) == 1,
            'jokertipp' => $jokertipp,
            'tendenzAbsolut' => ($cfg['showtendenzabs'] ?? null) == 1,
            'tendenzProzent' => ($cfg['showtendenzpro'] ?? null) == 1,
            'schnitt' => ($cfg['showdurchschntipp'] ?? null) == 1,
            'zeilen' => $zeilen,
            'keineSpiele' => $zeilen === [] ? Tipprahmen::meldung($kontext, $t[262] ?? '', true) : '',
            'speicherbar' => $speicherbar,
            'zurueck' => $blaettern . ($start - $tage),
            'weiter' => $blaettern . ($start + $tage),
            'tage' => $tage,
        ]);
    }

    /**
     * Die Ligen im Ligenordner, fuer die der Tipper eine Tippdatei hat und die getippt werden
     * duerfen (immeralle, sonst ligenzutippen), nach Dateinamen.
     *
     * @param array<string, mixed> $cfg
     * @return list<string>
     */
    private function ligen(string $ordner, string $tipps, string $nick, array $cfg): array
    {
        $dateien = [];
        $verz = @opendir(substr($ordner, 0, -1));
        if ($verz === false) {
            return [];
        }
        $zuTippen = explode(',', is_string($cfg['ligenzutippen'] ?? null) ? $cfg['ligenzutippen'] : '');
        while (($datei = readdir($verz)) !== false) {
            if (file_exists($tipps . substr($datei, 0, -4) . '_' . $nick . '.tip')
                && (($cfg['immeralle'] ?? null) == 1 || in_array(substr($datei, 0, -4), $zuTippen))) {
                $dateien[] = $datei;
            }
        }
        closedir($verz);
        sort($dateien);

        return $dateien;
    }

    /**
     * Uebernimmt die Tipps aus dem Formular und schreibt sie je Liga in die Tippdatei und je
     * Spieltag in die Einsicht (akteinsicht). Die Meldung nach dem Speichern; null, wenn eine Datei
     * nicht geschrieben werden konnte.
     *
     * @param list<UebersichtSpiel> $spiele
     */
    private function speichere(Kontext $kontext, array $spiele, string $tipps, string $nick, Tippzeit $tippzeit): ?string
    {
        $cfg = $kontext->konfiguration->addon('tipp');
        $anfrage = $kontext->anfrage;
        $jetzt = time();
        foreach ($spiele as $i => $s) {
            if (!$tippzeit->aenderbar((int) $s->spiel->anstoss, $s->spiel->ersterTag, $s->spiel->letzterTag, null, 0, 0)) {
                continue;
            }
            $jokerfeld = 'xjokerspiel_' . $s->liga . '_' . $s->spieltag;
            if (($cfg['jokertipp'] ?? null) == 1 && $anfrage->gesetzt($jokerfeld)) {
                $s->joker = trim($anfrage->text($jokerfeld));
                if ($s->jokerBis > 0 && $s->jokerBis < $jetzt) {
                    // der Joker liegt auf einem Spiel, das schon begonnen hat
                    $s->joker = 0;
                }
            }
            if (($cfg['tippmodus'] ?? null) == 1) {
                $s->tippa = self::tore(trim($anfrage->text('xtippa' . $i)));
                $s->tippb = self::tore(trim($anfrage->text('xtippb' . $i)));
            } elseif (($cfg['tippmodus'] ?? null) == 0) {
                [$s->tippa, $s->tippb] = match ($anfrage->text('xtipp' . $i)) {
                    '1' => ['1', '0'],
                    '2' => ['0', '1'],
                    '3' => ['0', '0'],
                    default => ['-1', '-1'],
                };
            }
        }

        $meldung = '';
        $liga = [];
        $spieltag = [];
        foreach ($spiele as $i => $s) {
            $liga[] = $s;
            $spieltag[] = $s;
            $naechstes = $spiele[$i + 1] ?? null;
            if ($naechstes === null || $naechstes->liga != $s->liga) {
                if (!Tippuebersicht::speichereTippdatei($tipps . $s->liga . '_' . $nick . '.tip', $liga)) {
                    return null;
                }
                if ($meldung === '') {
                    $meldung = Tipprahmen::meldung($kontext, $kontext->texteVon('tipp')[41] ?? '');
                }
                $liga = [];
            }
            if (($cfg['akteinsicht'] ?? null) == 1 && ($naechstes === null || $naechstes->spieltag != $s->spieltag || $naechstes->liga != $s->liga)) {
                if (!Tippuebersicht::speichereEinsicht($tipps . 'einsicht/' . $s->liga . '_' . $s->spieltag . '.ein', $nick, $spieltag)) {
                    return null;
                }
            }
            if ($naechstes === null || $naechstes->spieltag != $s->spieltag || $naechstes->liga != $s->liga) {
                $spieltag = [];
            }
        }

        return $meldung;
    }

    /** Ein eingegebener Tipp: leer, negativ oder "_" ist keiner (-1), sonst die Zahl. */
    private static function tore(string $wert): int
    {
        if ($wert == '' || $wert < 0 || $wert == '_') {
            return -1;
        }

        return intval($wert);
    }

    /**
     * Was die anderen Tipper getippt haben (Einsicht je Spieltag), je Spiel der Uebersicht; nur,
     * wenn eine Tendenz oder der Durchschnittstipp angezeigt wird.
     *
     * @param array<string, mixed> $cfg
     * @param list<UebersichtSpiel> $spiele
     * @return array<int, array{t1: int, t0: int, t2: int, a: int|float, b: int|float, anz: int}>
     */
    private function statistik(array $cfg, array $spiele, string $tipps): array
    {
        if (!(($cfg['showtendenzabs'] ?? null) == 1 || ($cfg['showtendenzpro'] ?? null) == 1 || (($cfg['showdurchschntipp'] ?? null) == 1 && ($cfg['tippmodus'] ?? null) == 1))) {
            return [];
        }
        $zahlen = [];
        $einsichten = [];
        foreach ($spiele as $i => $s) {
            $pfad = $tipps . 'einsicht/' . $s->liga . '_' . $s->spieltag . '.ein';
            $einsichten[$pfad] ??= Einsichtstatistik::lies($pfad, $s->pokal());
            $einsicht = $einsichten[$pfad];
            if ($einsicht !== null) {
                $zahlen[$i] = $s->pokal()
                    ? $einsicht->spiel((int) substr($s->nr, 0, -1) - 1, (int) substr($s->nr, -1) - 1)
                    : $einsicht->spiel((int) $s->nr - 1);
            }
        }

        return $zahlen;
    }

    /**
     * Die Kopfzeile eines Spieltags: Spieltag oder Pokalrunde (mit Link zur Einsicht) und Datum.
     *
     * @return array{name: string, link: string|null, datum: string, faktor: string, ohneUnentschieden: bool}
     */
    private function kopf(Kontext $kontext, UebersichtSpiel $s): array
    {
        $text = $kontext->text;
        $spiel = $s->spiel;
        if ($spiel->typ == 0) {
            $name = $s->spieltag . '. ' . ($text[2] ?? '');
        } else {
            $name = match (true) {
                $s->spieltag == $spiel->runden => $text[374] ?? '',
                $s->spieltag == $spiel->runden - 1 => $text[373] ?? '',
                $s->spieltag == $spiel->runden - 2 => $text[372] ?? '',
                $s->spieltag == $spiel->runden - 3 => $text[371] ?? '',
                default => $s->spieltag . '. ' . ($text[370] ?? ''),
            };
        }
        $datum = '';
        if ($spiel->mitSpieltagsdatum) {
            $datum .= $spiel->ersterTag != '' ? ' ' . ($text[3] ?? '') . ' ' . $spiel->ersterTag : '';
            $datum .= $spiel->letzterTag != '' ? ' ' . ($text[4] ?? '') . ' ' . $spiel->letzterTag : '';
        }

        return [
            'name' => $name,
            'link' => ($kontext->konfiguration->addon('tipp')['tippeinsicht'] ?? null) == 1 ? $kontext->adresse . '?action=tipp&amp;todo=einsicht&amp;file=' . $s->liga . '.l98&amp;st=' . $s->spieltag : null,
            'datum' => $datum,
            'faktor' => $spiel->torfaktor > 0 && $spiel->torfaktor != 1 ? '(' . ($text[553 + (int) log10($spiel->torfaktor)] ?? '') . ')' : '',
            'ohneUnentschieden' => $spiel->ohneUnentschieden,
        ];
    }

    /**
     * Eine Zeile der Uebersicht.
     *
     * @param array{t1: int, t0: int, t2: int, a: int|float, b: int|float, anz: int}|null $zahlen
     * @return array<string, mixed>
     */
    private function zeile(Kontext $kontext, UebersichtSpiel $s, int $i, ?array $zahlen, Punkte $regeln, Tippzeit $tippzeit): array
    {
        $cfg = $kontext->konfiguration->addon('tipp');
        $text = $kontext->text;
        $t = $kontext->texteVon('tipp');
        $spiel = $s->spiel;
        $zahlen ??= ['t1' => 0, 't0' => 0, 't2' => 0, 'a' => 0, 'b' => 0, 'anz' => 0];
        // die Tipps der anderen erst nach dem Ergebnis (einsichterst=2)
        $verborgen = ($cfg['einsichterst'] ?? null) == 2 && !($spiel->toreHeim != '_' && $spiel->toreGast != '_');
        $anstoss = '';
        if ($spiel->mitAnstosszeit && $spiel->anstoss > 0) {
            $anstoss = KalenderController::uebersetzt(date($kontext->konfiguration->datumsformat(), (int) $spiel->anstoss), $kontext->texteVon('date'));
        }
        if ($s->tippa == '_' || $s->tippb == '_' || $s->tippa == '-1' || $s->tippb == '-1') {
            $s->tippa = '';
            $s->tippb = '';
        }
        $prozent = static fn (int $anzahl): string => number_format($anzahl / $zahlen['anz'] * 100, 0, '.', ',') . '%';
        $nachkomma = $zahlen['a'] < 10 && $zahlen['b'] < 10 ? 1 : 0;
        $aenderbar = $tippzeit->aenderbar((int) $spiel->anstoss, $spiel->ersterTag, $spiel->letzterTag, null, 0, 1);
        $tendenz = -1;
        if ($s->tippa == '' || $s->tippb == '') {
            $tendenz = -1;
        } elseif ($s->tippa > $s->tippb) {
            $tendenz = 1;
        } elseif ($s->tippa == $s->tippb) {
            $tendenz = 0;
        } elseif ($s->tippa < $s->tippb) {
            $tendenz = 2;
        }
        $jokerOffen = $aenderbar && !($s->jokerBis > 0 && $s->jokerBis < time());

        // die Punkte fuer den Tipp
        $faktor = ($cfg['jokertipp'] ?? null) == 1 && $s->joker == $s->nr ? (is_numeric($cfg['jokertippmulti'] ?? null) ? +$cfg['jokertippmulti'] : 1) : 1;
        $punkte = -1;
        if ($s->tippa != '' && $s->tippb != '' && $spiel->toreHeim != '_' && $spiel->toreGast != '_') {
            [$punkte] = $regeln->fuer((int) $s->tippa, (int) $s->tippb, (int) $spiel->toreHeim, (int) $spiel->toreGast, (int) $spiel->wertung, (string) $spiel->zusatz, $text[0] ?? '', $text[1] ?? '', $faktor, $spiel->ohneTippwertung == 1);
        }
        if ($punkte == -1) {
            $punkteHtml = '-';
        } elseif ($punkte == -2) {
            $punkteHtml = $t[230] ?? '';
        } elseif (($cfg['tippmodus'] ?? null) == 1) {
            $punkteHtml = (string) $punkte;
        } elseif ($punkte > 0) {
            $punkteHtml = '<img src="' . $kontext->pfade->bilder . '/right.svg" height="20" border="0" alt="">' . ($punkte > 1 ? '+' . ($punkte - 1) : '');
        } else {
            $punkteHtml = '<img src="' . $kontext->pfade->bilder . '/wrong.svg" height="20" border="0" alt="">';
        }

        return [
            'i' => $i,
            'titel' => $spiel->titel,
            'anstoss' => $anstoss,
            'heim' => $spiel->heim,
            'gast' => $spiel->gast,
            'tendenzAbsolut' => $verborgen ? '' : $zahlen['t1'] . '-' . $zahlen['t0'] . '-' . $zahlen['t2'],
            'tendenzProzent' => $verborgen ? '' : ($zahlen['anz'] > 0 ? $prozent($zahlen['t1']) . '-' . $prozent($zahlen['t0']) . '-' . $prozent($zahlen['t2']) : '&nbsp;'),
            'schnitt' => $verborgen ? '' : ($zahlen['anz'] > 0 ? number_format($zahlen['a'] / $zahlen['anz'], $nachkomma, '.', ',') . ':' . number_format($zahlen['b'] / $zahlen['anz'], $nachkomma, '.', ',') : '&nbsp;'),
            'aenderbar' => $aenderbar,
            'tippa' => $s->tippa,
            'tippb' => $s->tippb,
            'tendenz' => $tendenz,
            'ohneUnentschieden' => $spiel->ohneUnentschieden,
            'jokerName' => 'xjokerspiel_' . $s->liga . '_' . $s->spieltag,
            'nr' => $s->nr,
            'joker' => $s->joker == $s->nr,
            'jokerOffen' => $jokerOffen,
            'toreHeim' => Darstellung::mitFaktor($spiel->toreHeim, $spiel->torfaktor),
            'toreGast' => Darstellung::mitFaktor($spiel->toreGast, $spiel->torfaktor),
            'zusatz' => $spiel->zusatz,
            'punkte' => $punkteHtml,
            'hinweis' => $this->hinweis($kontext, $spiel),
        ];
    }

    /** Spielbericht und Notiz zu einem Spiel als Symbole mit Tooltip, '&nbsp;' ohne. */
    private function hinweis(Kontext $kontext, AnstehendesSpiel $spiel): string
    {
        $text = $kontext->text;
        $bilder = $kontext->pfade->bilder;
        $html = '';
        // Mannschaftsicons finden
        $heimIcon = '';
        $gastIcon = '';
        if ($spiel->mitSpielbericht || $spiel->notiz != '' || $spiel->wertung > 0) {
            $heimIcon = $kontext->darstellung->wappen('', $spiel->heim, ' alt=""') . '&nbsp;';
            $gastIcon = $kontext->darstellung->wappen('', $spiel->gast, ' alt=""') . '&nbsp;';
        }
        // Spielbericht verlinken
        if ($spiel->mitSpielbericht) {
            if ($spiel->bericht != '') {
                $info = '<strong>' . $spiel->heim . $heimIcon . ' - ' . $gastIcon . $spiel->gast . '</strong>' . nl2br($text[270] ?? '');
                $html .= '<span onmouseover="showLMOTooltip(this)" onmouseout="hideLMOTooltip()" data-info="' . htmlspecialchars($info) . '" class="tooltip-trigger"><a href="' . $spiel->bericht . '" target="_blank"><img src="' . $bilder . '/lmo-st1.svg" height="15" class="svg-no-style" border="0" alt=""></a></span>&nbsp;';
            } else {
                $html .= '&nbsp;';
            }
        }
        // Notizen anzeigen
        if ($spiel->notiz != '' || $spiel->wertung > 0 || $spiel->ohneTippwertung > 0) {
            $tore = static fn (int|string $wert): string => (string) Darstellung::mitFaktor($wert, $spiel->torfaktor);
            // Gruener Tisch: beidseitiges Ergebnis vorbereiten
            $beide = $spiel->wertung == 1 ? ' / ' . $tore($spiel->toreGast) . ':' . $tore($spiel->toreHeim) : '';
            $notiz = '<strong>' . $spiel->heim . $heimIcon . ' - ' . $gastIcon . $spiel->gast . '&nbsp;&nbsp;' . $tore($spiel->toreHeim) . ':' . $tore($spiel->toreGast) . $beide . '</strong>';
            if ($spiel->mitZusatz) {
                $notiz .= '&nbsp;' . $spiel->zusatz . '<br>';
            }
            // Gruener Tisch: Heim- oder Gastteam siegt, beidseitiges Ergebnis
            if ($spiel->wertung == 1) {
                $notiz .= '<strong>' . ($text[219] ?? '') . ':</strong>' . $spiel->heim . '&nbsp;' . ($text[211] ?? '') . '<br>';
            }
            if ($spiel->wertung == 2) {
                $notiz .= '<strong>' . ($text[219] ?? '') . ':</strong>' . $spiel->gast . '&nbsp;' . ($text[211] ?? '') . '<br>';
            }
            if ($spiel->wertung == 3) {
                $notiz .= '<strong>' . ($text[219] ?? '') . ':</strong>' . ($text[212] ?? '') . '<br>';
            }
            // Allgemeine Notiz
            if ($spiel->notiz != '') {
                $notiz .= '<strong>' . ($text[22] ?? '') . ':</strong>' . $spiel->notiz . '<br>';
            }
            // Notiz zum Tippspiel
            if ($spiel->ohneTippwertung == 1) {
                $notiz .= '<br><strong>' . ($text[487] ?? '') . '</strong>' . ($kontext->texteVon('tipp')[231] ?? '');
            }
            $html .= '<span onmouseover="showLMOTooltip(this)" onmouseout="hideLMOTooltip()" data-info="' . htmlspecialchars(nl2br($notiz)) . '" class="tooltip-trigger"><img src="' . $bilder . '/lmo-st2.svg" height="15" class="svg-no-style" alt=""></span>';
        } else {
            $html .= '&nbsp;';
        }

        return $html;
    }
}
